<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Support\Notifications\NotificationCatalog;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NotificationCatalog::definitions() as $eventKey => $template) {
            unset($template['placeholders']);
            NotificationTemplate::firstOrCreate(
                ['event_key' => $eventKey],
                $template
            );
        }
    }
}
