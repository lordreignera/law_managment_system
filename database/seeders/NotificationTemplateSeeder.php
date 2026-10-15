<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Leave Request Submitted', 'event_key' => 'leave.request.submitted', 'subject' => 'Leave request submitted', 'body' => 'A leave request from {{ user_name }} is awaiting review.'],
            ['name' => 'Requisition Submitted', 'event_key' => 'requisition.submitted', 'subject' => 'Requisition submitted', 'body' => 'Requisition {{ reference_no }} is awaiting approval.'],
            ['name' => 'Court Event Reminder', 'event_key' => 'litigation.court_event.reminder', 'subject' => 'Upcoming court event', 'body' => '{{ matter_reference }} has an upcoming {{ event_type }} on {{ starts_at }}.'],
        ] as $index => $template) {
            NotificationTemplate::firstOrCreate(
                ['event_key' => $template['event_key']],
                $template + ['sort_order' => $index + 1, 'channel' => 'database']
            );
        }
    }
}
