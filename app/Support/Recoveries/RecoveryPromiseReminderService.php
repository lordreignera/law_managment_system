<?php

namespace App\Support\Recoveries;

use App\Models\RecoveryActivity;
use App\Models\RecoveryPromiseReminderLog;
use App\Models\User;
use App\Support\Sms\SmsGateway;
use Illuminate\Support\Carbon;

class RecoveryPromiseReminderService
{
    public function __construct(private readonly SmsGateway $gateway) {}

    /**
     * Send one reminder for each promised payment due on or before the target date.
     * Existing successful logs make this operation safe to run repeatedly.
     *
     * @return array{sent:int, skipped:int, failed:int}
     */
    public function sendDue(?Carbon $targetDate = null, ?User $user = null): array
    {
        $targetDate ??= now()->startOfDay();
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        RecoveryActivity::with(['account.client'])
            ->where('response_outcome', 'promised')
            ->whereNotNull('promised_on')
            ->whereDate('promised_on', '<=', $targetDate->toDateString())
            ->whereHas('account', function ($query) use ($user) {
                $query
                    ->when($user, fn ($query) => $query->forBranchOf($user))
                    ->where('status', 'active')
                    ->where('sms_opt_in', true)
                    ->where(function ($query) {
                        $query->whereNotNull('phone')->orWhereNotNull('alternate_phone')->orWhereNotNull('whatsapp_number');
                    });
            })
            ->get()
            ->each(function (RecoveryActivity $activity) use (&$sent, &$skipped, &$failed) {
                $account = $activity->account;
                $phone = $account?->phone ?: ($account?->alternate_phone ?: $account?->whatsapp_number);
                if (! $account || ! $phone) {
                    $skipped++;

                    return;
                }

                $log = RecoveryPromiseReminderLog::query()->firstOrCreate(
                    [
                        'recovery_activity_id' => $activity->id,
                        'scheduled_for' => $activity->promised_on,
                    ],
                    [
                        'recovery_account_id' => $account->id,
                        'recipient_phone' => $phone,
                        'status' => 'pending',
                    ]
                );

                if ($log->status === 'sent') {
                    $skipped++;

                    return;
                }

                $message = sprintf(
                    'Reminder from %s: your promised payment of %s %s for account %s is due. Please contact us if you need assistance.',
                    $account->client?->name ?: config('app.name'),
                    $account->currency ?: 'UGX',
                    number_format((float) $activity->promised_amount, 2),
                    $account->account_number ?: $account->customer_number ?: $account->debtor_name,
                );

                try {
                    $result = $this->gateway->send([$phone], $message);
                    if (! $result->successful) {
                        throw new \RuntimeException($result->error ?: 'The SMS gateway rejected the reminder.');
                    }

                    $log->update([
                        'recipient_phone' => $phone,
                        'message' => $message,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'error' => null,
                        'gateway_response' => $result->raw,
                    ]);
                    $sent++;
                } catch (\Throwable $exception) {
                    $log->update([
                        'recipient_phone' => $phone,
                        'message' => $message,
                        'status' => 'failed',
                        'error' => $exception->getMessage(),
                    ]);
                    $failed++;
                }
            });

        return compact('sent', 'skipped', 'failed');
    }
}
