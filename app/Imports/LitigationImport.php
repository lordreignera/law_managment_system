<?php

namespace App\Imports;

use App\Models\CourtEvent;
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

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $matterRef = $this->str($row['matter_ref'] ?? null);
            $startsAt = $this->dateTime($row['starts_at'] ?? null);

            if (! $matterRef || ! $startsAt) {
                $this->skipped++;

                continue;
            }

            $matter = Matter::where('reference_no', $matterRef)->first();

            if (! $matter) {
                $this->skipped++;

                continue;
            }

            $assignedTo = null;
            if ($email = $this->str($row['assigned_to_email'] ?? null)) {
                $assignedTo = User::where('email', $email)->value('id');
            }

            CourtEvent::create([
                'matter_id' => $matter->id,
                'assigned_to' => $assignedTo,
                'court_name' => $this->str($row['court_name'] ?? null),
                'case_number' => $this->str($row['case_number'] ?? null),
                'judicial_officer' => $this->str($row['judicial_officer'] ?? null),
                'event_type' => $this->str($row['event_type'] ?? null) ?: 'mention',
                'starts_at' => $startsAt,
                'ends_at' => $this->dateTime($row['ends_at'] ?? null),
                'status' => $this->str($row['status'] ?? null) ?: 'scheduled',
                'notes' => $this->str($row['notes'] ?? null),
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
}
