<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Notifications\CalendarEventReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendCalendarReminders extends Command
{
    protected $signature = 'kfms:calendar-reminders
                            {--days=1 : Number of days ahead to remind for}';

    protected $description = 'Send SMS reminders to assigned staff for upcoming calendar events (meetings, appointments, reminders).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $target = now()->addDays($days)->toDateString();

        $events = CalendarEvent::with(['assignee.staffProfile'])
            ->where('status', 'scheduled')
            ->whereNull('reminded_at')
            ->whereDate('starts_at', $target)
            ->get();

        if ($events->isEmpty()) {
            $this->info('No calendar events scheduled for '.$target.'.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($events as $event) {
            if (! $event->assignee || ! $event->assignee->routeNotificationForSms()) {
                continue;
            }

            Notification::send($event->assignee, new CalendarEventReminder($event));
            $event->forceFill(['reminded_at' => now()])->save();
            $sent++;
        }

        $this->info("Calendar reminders dispatched: {$sent} of {$events->count()} events.");

        return self::SUCCESS;
    }
}
