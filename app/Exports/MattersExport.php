<?php

namespace App\Exports;

use App\Models\Matter;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class MattersExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private User $user)
    {
    }

    public function query()
    {
        return Matter::query()
            ->forBranchOf($this->user)
            ->with(['client', 'practiceArea', 'matterCategory', 'branch'])
            ->withCount('files')
            ->latest();
    }

    public function headings(): array
    {
        return [
            'Reference No',
            'Title',
            'Client',
            'Client No',
            'Practice Area',
            'Category',
            'Status',
            'Opened On',
            'Closed On',
            'Privacy',
            'Opposite Counsel',
            'Branch',
            'Files',
            'Description',
        ];
    }

    /**
     * @param  Matter  $matter
     */
    public function map($matter): array
    {
        return [
            $matter->reference_no,
            $matter->title,
            $matter->client?->display_name ?: $matter->client?->name,
            $matter->client?->client_no,
            $matter->practiceArea?->name,
            $matter->matterCategory?->name,
            $matter->statusLabel(),
            optional($matter->opened_on)->format('Y-m-d'),
            optional($matter->closed_on)->format('Y-m-d'),
            $matter->privacy_status,
            $matter->opposite_counsel,
            $matter->branch?->name,
            $matter->files_count,
            $matter->description,
        ];
    }

    public function title(): string
    {
        return 'Matters';
    }
}
