<?php

namespace App\Console\Commands;

use App\Models\CourtEvent;
use App\Notifications\CourtEventReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendCourtReminders extends Command
{
    protected $signature = 'kfms:court-reminders
                            {--days=1 : Number of days ahead to remind for}';

    protected $description = 'Send SMS reminders to assigned advocates for upcoming court events.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $target = now()->addDays($days)->toDateString();

        $events = CourtEvent::with(['matter', 'court', 'assignee.staffProfile'])
            ->where('status', 'scheduled')
            ->whereDate('starts_at', $target)
            ->get();

        if ($events->isEmpty()) {
            $this->info('No court events scheduled for '.$target.'.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($events as $event) {
            if (! $event->assignee || ! $event->assignee->routeNotificationForSms()) {
                continue;
            }

            Notification::send($event->assignee, new CourtEventReminder($event));
            $sent++;
        }

        $this->info("Court reminders dispatched: {$sent} of {$events->count()} events.");

        return self::SUCCESS;
    }
}
