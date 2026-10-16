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
            ->with(['bank', 'bankBranch', 'zonalOffice', 'matter', 'handler'])
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
                        ->orWhereHas('bank', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('bankBranch', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('zonalOffice', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(($this->filters['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
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
            $title->notes,
            optional($title->created_at)->format('Y-m-d'),
        ];
    }

    public function title(): string
    {
        return 'Securities';
    }
}
