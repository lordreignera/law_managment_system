<?php

namespace App\Exports;

use App\Models\LandTitle;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class LandTitlesExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private User $user, private array $filters = []) {}

    public function query()
    {
        return LandTitle::query()->forBranchOf($this->user)
            ->with(['bank', 'bankBranch', 'zonalOffice', 'matter', 'client', 'handler'])
            ->when(($this->filters['search'] ?? null), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('borrower_name', 'like', "%{$search}%")
                        ->orWhere('title_number', 'like', "%{$search}%")
                        ->orWhere('land_reference', 'like', "%{$search}%")
                        ->orWhere('instruction_type', 'like', "%{$search}%")
                        ->orWhere('received_from', 'like', "%{$search}%")
                        ->orWhere('returned_to', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($query) => $query
                            ->where('client_no', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('organization_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"))
                        ->orWhereHas('matter', fn ($query) => $query
                            ->where('reference_no', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%"))
                        ->orWhereHas('bank', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('bankBranch', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('zonalOffice', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(($this->filters['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->when(($this->filters['movement_direction'] ?? null), fn ($query, $direction) => $query->where('movement_direction', $direction))
            ->when(($this->filters['security_type'] ?? null), fn ($query, $type) => $query->where('security_type', $type))
            ->when(($this->filters['bank_id'] ?? null), fn ($query, $bankId) => $query->where('bank_id', $bankId))
            ->when(($this->filters['bank_branch_id'] ?? null), fn ($query, $branchId) => $query->where('bank_branch_id', $branchId))
            ->when(($this->filters['zonal_office_id'] ?? null), fn ($query, $officeId) => $query->where('zonal_office_id', $officeId))
            ->when(($this->filters['handled_by'] ?? null), fn ($query, $handlerId) => $query->where('handled_by', $handlerId))
            ->when(($this->filters['received_from'] ?? null), fn ($query, $date) => $query->whereDate('received_at', '>=', $date))
            ->when(($this->filters['received_to'] ?? null), fn ($query, $date) => $query->whereDate('received_at', '<=', $date))
            ->latest();
    }

    public function headings(): array
    {
        return [
            'Reference No',
            'Security Type',
            'Direction',
            'Borrower',
            'Borrower Contact',
            'Client',
            'Title Number',
            'Land Reference',
            'Tenure',
            'District',
            'Facility / Account No',
            'Bank',
            'Bank Branch / Source Office',
            'Received From',
            'Returned To',
            'MZO / Zonal Office',
            'Matter',
            'Instruction Type',
            'Instruction Details',
            'Instruction Date',
            'Received At',
            'Dispatched At',
            'Dispatched To',
            'Dispatch Acknowledged By',
            'Dispatch Acknowledged At',
            'Returned At',
            'Handled By',
            'Status',
            'Archived At',
            'Notes',
            'Created On',
        ];
    }

    /**
     * @param  LandTitle  $title
     */
    public function map($title): array
    {
        return [
            $title->reference_no,
            str($title->security_type)->headline()->toString(),
            str($title->movement_direction)->headline()->toString(),
            $title->borrower_name,
            $title->borrower_contact,
            $title->client?->display_name,
            $title->title_number,
            $title->land_reference,
            $title->tenure,
            $title->district,
            $title->facility_account_no,
            $title->bank?->name,
            $title->bankBranch?->name,
            $title->received_from,
            $title->returned_to,
            $title->zonalOffice?->name,
            $title->matter?->reference_no,
            $title->instruction_type,
            $title->instruction_details,
            optional($title->instruction_date)->format('Y-m-d'),
            optional($title->received_at)->format('Y-m-d H:i'),
            optional($title->dispatched_at)->format('Y-m-d H:i'),
            $title->dispatched_to,
            $title->dispatch_acknowledged_by,
            optional($title->dispatch_acknowledged_at)->format('Y-m-d H:i'),
            optional($title->returned_at)->format('Y-m-d H:i'),
            $title->handler?->name,
            $title->statusLabel(),
            optional($title->archived_at)->format('Y-m-d H:i'),
            $title->notes,
            optional($title->created_at)->format('Y-m-d'),
        ];
    }

    public function title(): string
    {
        return 'Securities';
    }
}
