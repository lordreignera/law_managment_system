<?php

namespace App\Console\Commands;

use App\Models\LandTitleMilestone;
use App\Models\LandTitleMilestoneReminderLog;
use App\Notifications\SecurityMilestoneReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendSecurityMilestoneReminders extends Command
{
    protected $signature = 'kfms:security-reminders';

    protected $description = 'Send reminders for upcoming and overdue security milestones.';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        LandTitleMilestone::with(['landTitle.handler'])
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->get()
            ->each(function (LandTitleMilestone $milestone) use ($today, &$sent) {
                $type = $milestone->due_at->isPast() ? 'overdue' : ($milestone->due_at->copy()->startOfDay()->subDays($milestone->reminder_days_before)->equalTo($today) ? 'upcoming' : null);
                if (! $type || ! $milestone->landTitle?->handler) {
                    return;
                }

                $log = LandTitleMilestoneReminderLog::query()
                    ->where('land_title_milestone_id', $milestone->id)
                    ->where('recipient_id', $milestone->landTitle->handler->id)
                    ->where('reminder_type', $type)
                    ->whereDate('target_date', $today->toDateString())
                    ->first();

                if (! $log) {
                    $log = LandTitleMilestoneReminderLog::create([
                        'land_title_milestone_id' => $milestone->id,
                        'recipient_id' => $milestone->landTitle->handler->id,
                        'reminder_type' => $type,
                        'target_date' => $today->toDateString(),
                        'status' => 'pending',
                    ]);
                }

                if ($log->status === 'sent') {
                    return;
                }

                try {
                    Notification::send($milestone->landTitle->handler, new SecurityMilestoneReminder($milestone, $type));
                    $log->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
                    $sent++;
                } catch (\Throwable $exception) {
                    $log->update(['status' => 'failed', 'error' => $exception->getMessage()]);
                }
            });

        $this->info("Security milestone reminders dispatched: {$sent}.");

        return self::SUCCESS;
    }
}
