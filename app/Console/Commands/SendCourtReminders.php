<?php

namespace App\Console\Commands;

use App\Models\CourtEvent;
use App\Models\CourtEventReminderLog;
use App\Models\NotificationTemplate;
use App\Notifications\CourtEventDeadlineReminder;
use App\Notifications\CourtEventEscalation;
use App\Notifications\CourtEventReminder;
use App\Support\Notifications\NotificationCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class SendCourtReminders extends Command
{
    protected $signature = 'kfms:court-reminders
                            {--days=1 : Number of days ahead to remind for}';

    protected $description = 'Send court reminders, deadline notices, and overdue escalations.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $target = now()->addDays($days)->startOfDay();
        $lookAhead = $target->copy()->addDays(30);

        $events = CourtEvent::with([
            'matter.assignments.user',
            'matter.openedBy.manager',
            'court',
            'assignee.manager',
        ])
            ->where('reminders_enabled', true)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($target, $lookAhead) {
                $query
                    ->whereBetween('starts_at', [$target, $lookAhead->copy()->endOfDay()])
                    ->orWhereBetween('next_step_due', [$target->toDateString(), $lookAhead->toDateString()]);
            })
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $reminderDays = (int) $event->reminder_days_before;
            $eventReminderDate = $event->starts_at?->copy()->startOfDay()->subDays($reminderDays);
            $deadlineReminderDate = $event->next_step_due?->copy()->startOfDay()->subDays($reminderDays);

            if ($event->assignee && in_array($event->status, ['scheduled', 'adjourned'], true) && $eventReminderDate?->equalTo($target)) {
                $result = $this->sendOnce($event, $event->assignee, 'upcoming', $target, new CourtEventReminder($event));
                $sent += $result === 'sent' ? 1 : 0;
                $skipped += $result === 'skipped' ? 1 : 0;
            }

            if ($event->assignee && $deadlineReminderDate?->equalTo($target)) {
                $result = $this->sendOnce($event, $event->assignee, 'deadline', $target, new CourtEventDeadlineReminder($event));
                $sent += $result === 'sent' ? 1 : 0;
                $skipped += $result === 'skipped' ? 1 : 0;
            }
        }

        $overdueEvents = CourtEvent::with([
            'matter.assignments.user',
            'matter.openedBy.manager',
            'assignee.manager',
        ])
            ->where('reminders_enabled', true)
            ->whereIn('status', ['scheduled', 'adjourned'])
            ->where(function ($query) {
                $query->where('starts_at', '<', now())->orWhere('next_step_due', '<', today());
            })
            ->get();

        foreach ($overdueEvents as $event) {
            $escalationAt = $event->starts_at?->copy()->addDays((int) $event->escalation_days_after);
            $deadlineEscalationAt = $event->next_step_due?->copy()->startOfDay()->addDays((int) $event->escalation_days_after);
            $dueAt = collect([$escalationAt, $deadlineEscalationAt])->filter()->sort()->first();

            if (! $dueAt || $dueAt->isFuture()) {
                continue;
            }

            foreach ($this->escalationRecipients($event) as $recipient) {
                $result = $this->sendOnce($event, $recipient, 'escalation', now()->startOfDay(), new CourtEventEscalation($event));
                $sent += $result === 'sent' ? 1 : 0;
                $skipped += $result === 'skipped' ? 1 : 0;
            }
        }

        $this->info("Court notifications dispatched: {$sent}; skipped: {$skipped}; events checked: {$events->count()}.");

        return self::SUCCESS;
    }

    private function sendOnce(CourtEvent $event, $recipient, string $type, Carbon $scheduledFor, object $notification): string
    {
        $alreadyLogged = CourtEventReminderLog::query()
            ->where('court_event_id', $event->id)
            ->where('recipient_id', $recipient->id)
            ->where('reminder_type', $type)
            ->whereDate('scheduled_for', $scheduledFor->toDateString())
            ->exists();

        if ($alreadyLogged) {
            return 'duplicate';
        }

        $channel = $this->channelFor($notification);
        $log = CourtEventReminderLog::create([
            'court_event_id' => $event->id,
            'recipient_id' => $recipient->id,
            'reminder_type' => $type,
            'scheduled_for' => $scheduledFor,
            'channel' => $channel,
            'status' => 'pending',
        ]);

        if (! $this->canReceive($recipient, $channel)) {
            $log->update(['status' => 'skipped', 'error_message' => 'Recipient has no configured '.$channel.' route.']);

            return 'skipped';
        }

        try {
            Notification::send($recipient, $notification);
            $log->update(['status' => 'sent', 'sent_at' => now()]);

            return 'sent';
        } catch (\Throwable $exception) {
            report($exception);
            $log->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);

            return 'skipped';
        }
    }

    private function escalationRecipients(CourtEvent $event)
    {
        return collect([
            $event->matter?->openedBy,
            $event->matter?->openedBy?->manager,
            $event->assignee?->manager,
        ])
            ->merge($event->matter?->assignments?->where('assignment_role', 'partner')->pluck('user') ?? [])
            ->filter()
            ->unique('id')
            ->values();
    }

    private function channelFor(object $notification): string
    {
        $eventKey = method_exists($notification, 'notificationEventKey') ? $notification->notificationEventKey() : null;
        $template = $eventKey ? NotificationTemplate::where('event_key', $eventKey)->first() : null;

        return $template?->channel ?: (NotificationCatalog::definitions()[$eventKey]['channel'] ?? 'database');
    }

    private function canReceive($recipient, string $channel): bool
    {
        return match ($channel) {
            'sms' => filled($recipient->routeNotificationForSms()),
            'email' => filled($recipient->email),
            default => true,
        };
    }
}
