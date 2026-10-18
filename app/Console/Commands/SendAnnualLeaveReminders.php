<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Models\LeaveYearReminder;
use App\Models\User;
use App\Notifications\AnnualLeaveReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendAnnualLeaveReminders extends Command
{
    protected $signature = 'kfms:leave-reminders
                            {--year= : The leave year to remind staff about}';

    protected $description = 'Remind active staff to schedule their configured annual leave entitlement.';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $sent = 0;

        User::query()
            ->with('staffProfile')
            ->where(function ($query) {
                $query->whereNull('account_type')->orWhere('account_type', '!=', 'client');
            })
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->chunkById(100, function ($users) use ($year, &$sent) {
                foreach ($users as $user) {
                    $scheduledDays = LeaveRequest::annualScheduledDaysFor($user, $year);
                    $requiredDays = LeaveRequest::annualEntitlementForUser($user, $year);

                    if ($scheduledDays >= $requiredDays) {
                        continue;
                    }

                    $reminder = LeaveYearReminder::firstOrCreate(
                        ['user_id' => $user->id, 'year' => $year],
                        ['sent_at' => now()]
                    );

                    if (! $reminder->wasRecentlyCreated) {
                        continue;
                    }

                    try {
                        Notification::send($user, new AnnualLeaveReminder($year, $scheduledDays, $requiredDays));
                        $sent++;
                    } catch (Throwable $exception) {
                        $reminder->delete();
                        report($exception);
                    }
                }
            });

        $this->info("Annual leave reminders dispatched: {$sent}.");

        return self::SUCCESS;
    }
}
