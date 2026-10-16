<?php

namespace App\Console\Commands;

use App\Support\Recoveries\RecoveryPromiseReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendRecoveryPromiseReminders extends Command
{
    protected $signature = 'kfms:recovery-reminders {--date= : Process reminders due on or before this date (Y-m-d)}';

    protected $description = 'Send SMS reminders for recovery promises due today or earlier.';

    public function handle(RecoveryPromiseReminderService $reminders): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : now()->startOfDay();
        $result = $reminders->sendDue($date);

        $this->info(sprintf(
            'Recovery promise reminders: %d sent, %d skipped, %d failed.',
            $result['sent'],
            $result['skipped'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
