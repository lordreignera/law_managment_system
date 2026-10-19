<?php

namespace App\Imports;

use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\Client;
use App\Models\LandTitle;
use App\Models\LandTitleChecklistItem;
use App\Models\LandTitleMovement;
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
    public function __construct(private ?User $user = null) {}

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
                $client = $this->client($this->value($row, ['client_no', 'client_reference', 'security_client_no']));
                $reference = $this->value($row, ['reference_no', 'reference', 'security_no']);

                $bankName = $this->value($row, ['bank', 'financial_institution', 'institution']);
                $branchName = $this->value($row, ['bank_branch', 'branch', 'source_branch', 'office_branch']);
                $zonalName = $this->value($row, ['mzo', 'zonal_office', 'zonal_office_name', 'mzo_zonal_office']);
                $handlerName = $this->value($row, ['handler', 'handled_by', 'person_handling', 'officer']);
                $matterReference = $this->value($row, ['matter', 'matter_reference', 'matter_ref']);
                $clientReference = $this->value($row, ['client_no', 'client_reference', 'security_client_no']);

                if ($bankName && ! $bank) {
                    throw new \RuntimeException("Bank '{$bankName}' was not found in the master data.");
                }
                if ($branchName && ! $branch) {
                    throw new \RuntimeException("Bank branch '{$branchName}' was not found for the selected bank.");
                }
                if ($branchName && ! $bank) {
                    throw new \RuntimeException('A bank is required when a bank branch is supplied.');
                }
                if ($zonalName && ! $zonalOffice) {
                    throw new \RuntimeException("Zonal office '{$zonalName}' was not found in the master data.");
                }
                if ($handlerName && ! $handler) {
                    throw new \RuntimeException("Active staff handler '{$handlerName}' was not found or is outside your branch.");
                }
                if ($matterReference && ! $matter) {
                    throw new \RuntimeException("Matter '{$matterReference}' was not found.");
                }
                if ($clientReference && ! $client) {
                    throw new \RuntimeException("Client '{$clientReference}' was not found.");
                }

                $existing = null;
                if ($reference) {
                    $existing = LandTitle::query()->where('reference_no', $reference)->first();
                    if ($existing && $this->user && ! LandTitle::query()->forBranchOf($this->user)->whereKey($existing->id)->exists()) {
                        throw new \RuntimeException('The reference belongs to a security outside your branch.');
                    }
                }

                $securityType = $this->securityType($this->value($row, ['security_type', 'security']));
                $direction = $this->direction($this->value($row, ['movement_direction', 'direction']));
                $status = $this->status($this->value($row, ['status']));

                $payload = [
                    'bank_id' => $bank?->id,
                    'branch_id' => $this->user?->branch_id,
                    'bank_branch_id' => $branch?->id,
                    'zonal_office_id' => $zonalOffice?->id,
                    'matter_id' => $matter?->id,
                    'client_id' => $client?->id,
                    'handled_by' => $handler?->id,
                    'security_type' => $securityType,
                    'movement_direction' => $direction,
                    'borrower_name' => $borrower,
                    'borrower_contact' => $this->value($row, ['borrower_contact', 'contact', 'phone']),
                    'title_number' => $this->value($row, ['title_number', 'title_no']),
                    'land_reference' => $this->value($row, ['land_reference', 'plot', 'block_plot', 'property_reference']),
                    'tenure' => $this->value($row, ['tenure']),
                    'district' => $this->value($row, ['district']),
                    'facility_account_no' => $this->value($row, ['facility_account_no', 'account_number', 'facility_no']),
                    'instruction_type' => $this->value($row, ['instruction_type', 'instruction']),
                    'instruction_details' => $this->value($row, ['instruction_details', 'details']),
                    'instruction_date' => $this->date($this->value($row, ['instruction_date'])),
                    'received_from' => $this->value($row, ['received_from', 'from']),
                    'returned_to' => $this->value($row, ['returned_to']),
                    'received_at' => $this->dateTime($this->value($row, ['received_at', 'date_received', 'received_date'])),
                    'dispatched_at' => $this->dateTime($this->value($row, ['dispatched_at', 'date_dispatched', 'dispatch_date'])),
                    'dispatched_to' => $this->value($row, ['dispatched_to', 'dispatch_to', 'recipient']),
                    'dispatch_acknowledged_by' => $this->value($row, ['dispatch_acknowledged_by', 'acknowledged_by']),
                    'dispatch_acknowledged_at' => $this->dateTime($this->value($row, ['dispatch_acknowledged_at', 'acknowledged_at'])),
                    'returned_at' => $this->dateTime($this->value($row, ['returned_at', 'date_returned', 'returned_date'])),
                    'status' => $status,
                    'notes' => $this->value($row, ['notes', 'remarks']),
                ];

                if ($matter && $this->user && ! Matter::visibleTo($this->user)->whereKey($matter->id)->exists()) {
                    throw new \RuntimeException('Matter is not visible to the importing user.');
                }
                if ($client && $this->user && ! Client::forBranchOf($this->user)->whereKey($client->id)->exists()) {
                    throw new \RuntimeException('Client is not visible to the importing user.');
                }
                if ($client && $matter && $matter->client_id && (int) $matter->client_id !== (int) $client->id) {
                    throw new \RuntimeException('The selected client does not match the matter client.');
                }

                $this->validateChronology($payload);
                if ($status === 'dispatched' && (empty($payload['dispatched_to']) || empty($payload['dispatched_at']))) {
                    throw new \RuntimeException('Dispatched records require dispatched_to and dispatched_at.');
                }
                if ($status === 'received' && empty($payload['received_at'])) {
                    throw new \RuntimeException('Received records require received_at.');
                }
                if (in_array($status, ['returned', 'closed', 'archived'], true)) {
                    throw new \RuntimeException('Returned, closed, or archived records must be completed through the workflow.');
                }

                // Imports may create or amend an active register entry, but they
                // must not rewrite a completed custody history or bypass the
                // lifecycle transition rules used by the web workflow.
                if ($existing) {
                    if (in_array($existing->status, ['returned', 'closed', 'archived'], true)) {
                        throw new \RuntimeException('Completed or archived securities cannot be amended by import.');
                    }

                    if (! $existing->canTransitionTo($status)) {
                        throw new \RuntimeException("A security cannot move from {$existing->statusLabel()} to ".(LandTitle::STATUSES[$status] ?? $status).' during import.');
                    }

                    // The template has no branch column. Preserve the owning
                    // branch when an all-branch administrator amends a record.
                    $payload['branch_id'] = $existing->branch_id;
                }

                if ($reference) {
                    $title = LandTitle::updateOrCreate(
                        ['reference_no' => $reference],
                        $payload
                    );
                } else {
                    $title = LandTitle::create($payload + [
                        'reference_no' => MonthlyReferenceNumber::make(LandTitle::class, 'reference_no', 'SEC'),
                    ]);
                }

                if ($title->checklistItems()->count() === 0) {
                    foreach (config('securities.checklists.'.$title->security_type, config('securities.checklists.other')) as $sortOrder => $label) {
                        LandTitleChecklistItem::create([
                            'land_title_id' => $title->id,
                            'label' => $label,
                            'is_required' => true,
                            'sort_order' => $sortOrder + 1,
                        ]);
                    }
                }

                $movementType = match ($title->status) {
                    'received' => 'received',
                    'dispatched' => 'dispatched',
                    'returned' => 'returned',
                    default => null,
                };
                if ($movementType && $title->movements()->where('movement_type', $movementType)->doesntExist()) {
                    LandTitleMovement::create([
                        'land_title_id' => $title->id,
                        'movement_type' => $movementType,
                        'from_party' => $title->received_from,
                        'to_party' => $movementType === 'dispatched' ? $title->dispatched_to : ($movementType === 'returned' ? $title->returned_to : null),
                        'moved_at' => match ($movementType) {
                            'received' => $title->received_at ?: now(),
                            'dispatched' => $title->dispatched_at ?: now(),
                            default => $title->returned_at ?: now(),
                        },
                        'recorded_by' => $this->user?->id,
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
        return $name ? Bank::where('name', $name)->where('is_active', true)->first() : null;
    }

    private function bankBranch(?Bank $bank, ?string $name): ?BankBranch
    {
        if (! $name) {
            return null;
        }

        return BankBranch::query()
            ->when($bank, fn ($query) => $query->where('bank_id', $bank->id))
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    private function zonalOffice(?string $name): ?ZonalOffice
    {
        return $name ? ZonalOffice::where('name', $name)->where('is_active', true)->first() : null;
    }

    private function handler(?string $nameOrEmail): ?User
    {
        if (! $nameOrEmail) {
            return null;
        }

        $query = User::query()
            ->where(function ($query) use ($nameOrEmail) {
                $query->where('email', $nameOrEmail)->orWhere('name', $nameOrEmail);
            });

        $handler = $query->whereHas('staffProfile', fn ($profile) => $profile->where('employment_status', 'active'))->first();
        if (! $handler || ($this->user && ! $this->user->canSeeAllBranches() && ! (($handler->branch_id && (int) $handler->branch_id === (int) $this->user->branch_id) || (int) $handler->staffProfile?->branch_id === (int) $this->user->branch_id))) {
            return null;
        }

        return $handler;
    }

    private function matter(?string $reference): ?Matter
    {
        return $reference ? Matter::where('reference_no', $reference)->first() : null;
    }

    private function client(?string $reference): ?Client
    {
        return $reference ? Client::where('client_no', $reference)->first() : null;
    }

    private function status(?string $status): string
    {
        $normalized = str($status ?: 'pending')->lower()->replace([' ', '-'], '_')->toString();
        if (! array_key_exists($normalized, LandTitle::STATUSES)) {
            throw new \RuntimeException("Invalid security status '{$status}'.");
        }

        return $normalized;
    }

    private function securityType(?string $type): string
    {
        $normalized = str($type ?: 'land_title')->lower()->replace([' ', '-'], '_')->toString();
        $normalized = ['title_instrument' => 'land_title', 'title' => 'land_title'][$normalized] ?? $normalized;
        if (! array_key_exists($normalized, LandTitle::SECURITY_TYPES)) {
            throw new \RuntimeException("Invalid security type '{$type}'.");
        }

        return $normalized;
    }

    private function direction(?string $direction): string
    {
        $normalized = str($direction ?: 'incoming')->lower()->replace([' ', '-'], '_')->toString();
        if (! in_array($normalized, ['incoming', 'outgoing'], true)) {
            throw new \RuntimeException("Invalid movement direction '{$direction}'.");
        }

        return $normalized;
    }

    private function validateChronology(array $payload): void
    {
        $dates = collect(['instruction_date', 'received_at', 'dispatched_at', 'dispatch_acknowledged_at', 'returned_at'])
            ->mapWithKeys(fn ($key) => [$key => ! empty($payload[$key]) ? Carbon::parse($payload[$key]) : null]);
        if ($dates['instruction_date'] && $dates['received_at'] && $dates['instruction_date']->gt($dates['received_at'])) {
            throw new \RuntimeException('Instruction date cannot be after receipt.');
        }
        if ($dates['received_at'] && $dates['dispatched_at'] && $dates['dispatched_at']->lt($dates['received_at'])) {
            throw new \RuntimeException('Dispatch cannot be before receipt.');
        }
        if ($dates['dispatched_at'] && $dates['dispatch_acknowledged_at'] && $dates['dispatch_acknowledged_at']->lt($dates['dispatched_at'])) {
            throw new \RuntimeException('Acknowledgement cannot be before dispatch.');
        }
        if ($dates['dispatched_at'] && $dates['returned_at'] && $dates['returned_at']->lt($dates['dispatched_at'])) {
            throw new \RuntimeException('Return cannot be before dispatch.');
        }
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
