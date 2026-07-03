<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\User;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;
    public int $skipped = 0;

    public function __construct(private User $user)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = $this->str($row['name'] ?? null);
            $first = $this->str($row['first_name'] ?? null);
            $last = $this->str($row['last_name'] ?? null);
            $org = $this->str($row['organization_name'] ?? null);
            $composed = $name ?: trim(($first ?? '').' '.($last ?? ''));

            if (($composed === '' || $composed === null) && ! $org) {
                $this->skipped++;

                continue;
            }

            $type = strtolower((string) ($this->str($row['client_type'] ?? null) ?? 'individual'));
            $type = in_array($type, ['individual', 'organization'], true) ? $type : 'individual';
            $gender = strtolower((string) ($this->str($row['gender'] ?? null) ?? ''));

            Client::create([
                'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
                'client_type' => $type,
                'name' => $composed ?: $org,
                'organization_name' => $org,
                'first_name' => $first,
                'last_name' => $last,
                'gender' => in_array($gender, ['male', 'female'], true) ? $gender : null,
                'nin_passport_no' => $this->str($row['nin_passport_no'] ?? null),
                'email' => $this->str($row['email'] ?? null),
                'phone' => $this->str($row['phone'] ?? null),
                'address' => $this->str($row['address'] ?? null),
                'occupation' => $this->str($row['occupation'] ?? null),
                'tin' => $this->str($row['tin'] ?? null),
                'status' => $this->str($row['status'] ?? null) ?: 'active',
                'branch_id' => $this->user->branch_id,
            ]);

            $this->imported++;
        }
    }

    private function str($value): ?string
    {
        $value = is_null($value) ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }
}
