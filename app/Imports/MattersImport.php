<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterCategory;
use App\Models\PracticeArea;
use App\Models\User;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MattersImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;
    public int $skipped = 0;

    public function __construct(private User $user)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $clientNo = $this->str($row['client_no'] ?? null);

            if (! $clientNo) {
                $this->skipped++;

                continue;
            }

            $client = Client::where('client_no', $clientNo)->first();

            // One matter per client: skip unknown clients or those that already have a matter.
            if (! $client || $client->matter) {
                $this->skipped++;

                continue;
            }

            $status = $this->str($row['status'] ?? null);
            $status = $status && array_key_exists($status, Matter::STATUSES) ? $status : 'open';

            $privacy = strtolower((string) ($this->str($row['privacy_status'] ?? null) ?? 'public'));
            $privacy = in_array($privacy, ['public', 'private'], true) ? $privacy : 'public';

            Matter::create([
                'client_id' => $client->id,
                'practice_area_id' => $this->lookupId(PracticeArea::class, $this->str($row['practice_area'] ?? null)),
                'matter_category_id' => $this->lookupId(MatterCategory::class, $this->str($row['matter_category'] ?? null)),
                'opened_by' => $this->user->id,
                'branch_id' => $this->user->branch_id,
                'department_id' => $this->user->department_id,
                'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
                'title' => $this->str($row['title'] ?? null) ?: ($client->display_name ?: $client->name),
                'opened_on' => $this->date($row['opened_on'] ?? null) ?: now()->toDateString(),
                'privacy_status' => $privacy,
                'opposite_counsel' => $this->str($row['opposite_counsel'] ?? null),
                'status' => $status,
                'description' => $this->str($row['description'] ?? null) ?: 'Imported matter.',
            ]);

            $this->imported++;
        }
    }

    private function lookupId(string $modelClass, ?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        return $modelClass::where('name', $name)->value('id');
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

    private function str($value): ?string
    {
        $value = is_null($value) ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }
}
