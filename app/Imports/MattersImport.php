<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\File;
use App\Models\InstructionType;
use App\Models\Matter;
use App\Models\MatterCategory;
use App\Models\OpposingCounsel;
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

    public function __construct(private User $user) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $clientNo = $this->str($row['client_no'] ?? null);

            if (! $clientNo) {
                $this->skipped++;

                continue;
            }

            $client = Client::forBranchOf($this->user)->where('client_no', $clientNo)->first();

            // Skip unknown clients; a client may have any number of matters.
            if (! $client) {
                $this->skipped++;

                continue;
            }

            $status = $this->str($row['status'] ?? null);
            $status = $status && array_key_exists($status, Matter::STATUSES) ? $status : 'open';

            $privacy = strtolower((string) ($this->str($row['privacy_status'] ?? null) ?? 'public'));
            $privacy = in_array($privacy, ['public', 'private'], true) ? $privacy : 'public';

            $matter = Matter::create([
                'client_id' => $client->id,
                'practice_area_id' => $this->lookupId(PracticeArea::class, $this->str($row['practice_area'] ?? null)),
                'instruction_type_id' => $this->lookupId(InstructionType::class, $this->str($row['instruction_type'] ?? null)),
                'matter_category_id' => $this->lookupId(MatterCategory::class, $this->str($row['matter_category'] ?? null)),
                'opened_by' => $this->user->id,
                'branch_id' => $this->user->branch_id,
                'department_id' => $this->user->department_id,
                'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
                'title' => $this->str($row['title'] ?? null) ?: ($client->display_name ?: $client->name),
                'opened_on' => $this->date($row['opened_on'] ?? null) ?: now()->toDateString(),
                'instruction_date' => $this->date($row['instruction_date'] ?? null),
                'privacy_status' => $privacy,
                'opposite_counsel' => $this->str($row['opposite_counsel'] ?? null),
                'opposing_counsel_id' => $this->lookupId(OpposingCounsel::class, $this->str($row['opposing_counsel_name'] ?? null)),
                'status' => $status,
                'description' => $this->str($row['description'] ?? null) ?: 'Imported matter.',
                'client_summary' => $this->str($row['client_summary'] ?? null),
                'next_action' => $this->str($row['next_action'] ?? null),
                'next_action_due' => $this->date($row['next_action_due'] ?? null),
            ]);

            $partnerEmails = $this->emails($row['partner_emails'] ?? null);
            $associateEmails = $this->emails($row['associate_emails'] ?? null);
            if ($partnerEmails === []) {
                $partnerEmails = [$this->user->email];
            }
            $partnerIds = $this->activeUsers($partnerEmails);
            $associateIds = $this->activeUsers($associateEmails);
            foreach ($partnerIds as $index => $userId) {
                $matter->assignments()->create([
                    'user_id' => $userId,
                    'assignment_role' => 'partner',
                    'assigned_on' => now()->toDateString(),
                    'is_lead' => $index === 0,
                ]);
            }
            foreach (array_diff($associateIds, $partnerIds) as $userId) {
                $matter->assignments()->create([
                    'user_id' => $userId,
                    'assignment_role' => 'associate',
                    'assigned_on' => now()->toDateString(),
                ]);
            }

            foreach ($this->split($row['file_numbers'] ?? null) as $fileNumber) {
                File::where('client_id', $client->id)
                    ->whereNull('matter_id')
                    ->where('file_number', $fileNumber)
                    ->get()
                    ->each(fn (File $file) => $file->update(['matter_id' => $matter->id]));
            }

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

    private function split($value): array
    {
        return collect(preg_split('/[|,]/', (string) $value))->map(fn ($item) => $this->str($item))->filter()->unique()->values()->all();
    }

    private function emails($value): array
    {
        return collect($this->split($value))->map(fn ($email) => strtolower($email))->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->values()->all();
    }

    private function activeUsers(array $emails): array
    {
        return User::query()->whereIn('email', $emails)
            ->where(function ($query) {
                $query->whereNull('account_type')->orWhere('account_type', 'staff');
            })
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->pluck('id')->all();
    }
}
