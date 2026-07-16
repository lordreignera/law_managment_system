<?php

namespace App\Imports;

use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\LandTitle;
use App\Models\Matter;
use App\Models\User;
use App\Models\ZonalOffice;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class LandTitlesImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $skipped = 0;

    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $borrower = $this->value($row, ['borrower_name', 'borrower', 'client', 'customer_name']);

                if (! $borrower) {
                    $this->skipped++;
                    $this->errors[] = "Row {$rowNumber}: borrower name is required.";
                    continue;
                }

                $bank = $this->bank($this->value($row, ['bank', 'financial_institution', 'institution']));
                $branch = $this->bankBranch($bank, $this->value($row, ['bank_branch', 'branch', 'source_branch', 'office_branch']));
                $zonalOffice = $this->zonalOffice($this->value($row, ['mzo', 'zonal_office', 'zonal_office_name', 'mzo_zonal_office']));
                $handler = $this->handler($this->value($row, ['handler', 'handled_by', 'person_handling', 'officer']));
                $matter = $this->matter($this->value($row, ['matter', 'matter_reference', 'matter_ref']));
                $reference = $this->value($row, ['reference_no', 'reference', 'security_no']);

                $payload = [
                    'bank_id' => $bank?->id,
                    'bank_branch_id' => $branch?->id,
                    'zonal_office_id' => $zonalOffice?->id,
                    'matter_id' => $matter?->id,
                    'handled_by' => $handler?->id,
                    'borrower_name' => $borrower,
                    'instruction_type' => $this->value($row, ['instruction_type', 'instruction']),
                    'instruction_date' => $this->date($this->value($row, ['instruction_date'])),
                    'received_from' => $this->value($row, ['received_from', 'from']),
                    'returned_to' => $this->value($row, ['returned_to']),
                    'received_at' => $this->dateTime($this->value($row, ['received_at', 'date_received', 'received_date'])),
                    'dispatched_at' => $this->dateTime($this->value($row, ['dispatched_at', 'date_dispatched', 'dispatch_date'])),
                    'returned_at' => $this->dateTime($this->value($row, ['returned_at', 'date_returned', 'returned_date'])),
                    'status' => $this->status($this->value($row, ['status'])),
                    'notes' => $this->value($row, ['notes', 'remarks']),
                ];

                if ($reference) {
                    LandTitle::updateOrCreate(
                        ['reference_no' => $reference],
                        $payload
                    );
                } else {
                    LandTitle::create($payload + [
                        'reference_no' => MonthlyReferenceNumber::make(LandTitle::class, 'reference_no', 'SEC'),
                    ]);
                }

                $this->imported++;
            } catch (Throwable $exception) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: {$exception->getMessage()}";
            }
        }
    }

    private function value(Collection $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row->get($key);

            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function bank(?string $name): ?Bank
    {
        return $name ? Bank::where('name', $name)->first() : null;
    }

    private function bankBranch(?Bank $bank, ?string $name): ?BankBranch
    {
        if (! $name) {
            return null;
        }

        return BankBranch::query()
            ->when($bank, fn ($query) => $query->where('bank_id', $bank->id))
            ->where('name', $name)
            ->first();
    }

    private function zonalOffice(?string $name): ?ZonalOffice
    {
        return $name ? ZonalOffice::where('name', $name)->first() : null;
    }

    private function handler(?string $nameOrEmail): ?User
    {
        if (! $nameOrEmail) {
            return null;
        }

        return User::query()
            ->where('email', $nameOrEmail)
            ->orWhere('name', $nameOrEmail)
            ->first();
    }

    private function matter(?string $reference): ?Matter
    {
        return $reference ? Matter::where('reference_no', $reference)->first() : null;
    }

    private function status(?string $status): string
    {
        $normalized = str($status ?: 'pending')->lower()->replace([' ', '-'], '_')->toString();

        return array_key_exists($normalized, LandTitle::STATUSES) ? $normalized : 'pending';
    }

    private function date(mixed $value): ?string
    {
        return $this->parseDate($value)?->toDateString();
    }

    private function dateTime(mixed $value): ?Carbon
    {
        return $this->parseDate($value);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        return Carbon::parse((string) $value);
    }
}
