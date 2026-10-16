<?php

namespace App\Support\Notifications;

class NotificationCatalog
{
    public static function definitions(): array
    {
        return [
            'leave.request.submitted' => self::item('Leave Approval Requested', 'email', 'Leave approval required: {{ reference_no }}', '{{ user_name }} requested {{ leave_type }} from {{ start_date }} to {{ end_date }} ({{ days }} days). Approval stage: {{ stage }}.', ['user_name', 'reference_no', 'leave_type', 'start_date', 'end_date', 'days', 'stage']),
            'leave.request.approved' => self::item('Leave Approved', 'email', 'Leave request approved: {{ reference_no }}', 'Your leave request {{ reference_no }} has received all required approvals.', ['reference_no', 'reviewer_name', 'review_notes']),
            'leave.request.rejected' => self::item('Leave Rejected', 'email', 'Leave request rejected: {{ reference_no }}', 'Your leave request {{ reference_no }} was rejected by {{ reviewer_name }}. {{ review_notes }}', ['reference_no', 'reviewer_name', 'review_notes']),
            'leave.request.progressed' => self::item('Leave Approval Progress', 'email', 'Leave request moved to {{ stage }}: {{ reference_no }}', 'Your leave request {{ reference_no }} passed the previous stage and now awaits {{ stage }}.', ['reference_no', 'stage', 'reviewer_name', 'review_notes']),
            'leave.annual.reminder' => self::item('Annual Leave Reminder', 'email', 'Annual leave reminder for {{ year }}', 'Please schedule your annual leave plan for {{ year }}. Scheduled: {{ scheduled_days }} days. Remaining: {{ remaining_days }} days.', ['year', 'scheduled_days', 'remaining_days']),
            'litigation.court_event.reminder' => self::item('Court Event Reminder', 'sms', 'Upcoming court event', 'Reminder: {{ event_type }} on {{ starts_at }} at {{ court_name }}. Case {{ case_number }}. Matter {{ matter_reference }}.', ['event_type', 'starts_at', 'court_name', 'case_number', 'matter_reference']),
            'litigation.court_event.deadline' => self::item('Court Deadline Reminder', 'email', 'Court deadline due: {{ matter_reference }}', 'Deadline reminder: {{ next_step }} for {{ matter_reference }} is due on {{ next_step_due }}. Case {{ case_number }}.', ['next_step', 'next_step_due', 'case_number', 'matter_reference']),
            'litigation.court_event.escalated' => self::item('Court Work Escalation', 'email', 'Overdue court work: {{ matter_reference }}', 'Escalation: {{ event_type }} for {{ matter_reference }} is overdue. Next step: {{ next_step }}. Please review the court file.', ['event_type', 'matter_reference', 'next_step', 'case_number']),
            'calendar.event.reminder' => self::item('Calendar Event Reminder', 'sms', 'Calendar reminder: {{ title }}', 'Reminder: {{ title }} on {{ starts_at }}. Location: {{ location }}.', ['title', 'starts_at', 'location']),
            'staff.account.approved' => self::item('Staff Account Approved', 'email', 'Your account has been approved', 'Your firm account is active. Assigned role: {{ role_name }}. You can now sign in.', ['role_name']),
            'staff.account.created' => self::item('Staff Account Created', 'email', 'Your firm account is ready', 'Your firm account has been created and activated. Assigned role: {{ role_name }}.', ['role_name']),
            'requisition.submitted' => self::item('Requisition Approval Requested', 'database', 'Requisition submitted: {{ reference_no }}', '{{ user_name }} submitted {{ reference_no }} for {{ amount }}. Purpose: {{ purpose }}.', ['reference_no', 'user_name', 'amount', 'purpose']),
            'requisition.decided' => self::item('Requisition Decision', 'database', 'Requisition {{ decision }}: {{ reference_no }}', 'Your requisition {{ reference_no }} was {{ decision }}. {{ review_notes }}', ['reference_no', 'decision', 'review_notes']),
        ];
    }

    private static function item(string $name, string $channel, string $subject, string $body, array $placeholders): array
    {
        return compact('name', 'channel', 'subject', 'body', 'placeholders');
    }

    public static function render(string $text, array $context): string
    {
        return preg_replace_callback('/{{\s*([a-z_]+)\s*}}/', fn ($match) => (string) ($context[$match[1]] ?? ''), $text);
    }
}
