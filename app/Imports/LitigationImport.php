<?php

namespace App\Imports;

use App\Models\Court;
use App\Models\CourtEvent;
use App\Models\JudicialOfficer;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LitigationImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $skipped = 0;

    public function __construct(private ?User $user = null) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $matterRef = $this->str($row['matter_ref'] ?? null);
            $startsAt = $this->dateTime($row['starts_at'] ?? null);
            $courtName = $this->str($row['court_name'] ?? null);

            if (! $matterRef || ! $startsAt || ! $courtName) {
                $this->skipped++;

                continue;
            }

            $matterQuery = Matter::where('reference_no', $matterRef)->visibleTo($this->user);
            $matter = $matterQuery->first();

            if (! $matter) {
                $this->skipped++;

                continue;
            }

            $assignedTo = null;
            if ($email = $this->str($row['assigned_to_email'] ?? null)) {
                $assignedTo = User::query()
                    ->where('email', $email)
                    ->where(function ($query) {
                        $query
                            ->whereNull('account_type')
                            ->orWhere('account_type', 'staff');
                    })
                    ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
                    ->value('id');
            }

            $eventType = $this->str($row['event_type'] ?? null);
            $eventType = $eventType && array_key_exists($eventType, CourtEvent::EVENT_TYPES) ? $eventType : 'mention';
            $status = $this->str($row['status'] ?? null);
            $status = $status && array_key_exists($status, CourtEvent::STATUSES) ? $status : 'scheduled';

            CourtEvent::create([
                'matter_id' => $matter->id,
                'court_id' => Court::where('name', $courtName)->value('id'),
                'judicial_officer_id' => JudicialOfficer::where('name', $this->str($row['judicial_officer'] ?? null))->value('id'),
                'assigned_to' => $assignedTo,
                'court_name' => $courtName,
                'case_number' => $this->str($row['case_number'] ?? null),
                'judicial_officer' => $this->str($row['judicial_officer'] ?? null),
                'event_type' => $eventType,
                'starts_at' => $startsAt,
                'ends_at' => $this->dateTime($row['ends_at'] ?? null),
                'status' => $status,
                'notes' => $this->str($row['notes'] ?? null),
                'next_step' => $this->str($row['next_step'] ?? null),
                'next_step_due' => $this->date($row['next_step_due'] ?? null),
                'reminder_days_before' => $this->integer($row['reminder_days_before'] ?? null, 1),
                'escalation_days_after' => $this->integer($row['escalation_days_after'] ?? null, 0),
                'reminders_enabled' => $this->boolean($row['reminders_enabled'] ?? null, true),
            ]);

            $this->imported++;
        }
    }

    private function dateTime($value): ?string
    {
        $value = $this->str($value);

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function str($value): ?string
    {
        $value = is_null($value) ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function date($value): ?string
    {
        $value = $this->str($value);
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function integer($value, int $default): int
    {
        return is_numeric($value) ? max(0, min(30, (int) $value)) : $default;
    }

    private function boolean($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
