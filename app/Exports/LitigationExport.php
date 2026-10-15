<?php

namespace App\Exports;

use App\Models\CourtEvent;
use App\Models\User;
use App\Support\Litigation\LitigationQueryFilters;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class LitigationExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private array $filters,
        private User $user,
    )
    {
    }

    public function query()
    {
        $query = LitigationQueryFilters::apply(
            CourtEvent::query()->with(['matter.client', 'court', 'assignee']),
            $this->filters,
            $this->user->id
        );

        $query->whereHas('matter', fn ($matterQuery) => $matterQuery->visibleTo($this->user));

        return $query->latest('starts_at');
    }

    public function headings(): array
    {
        return [
            'Matter Ref',
            'Client',
            'Court',
            'Case Number',
            'Judicial Officer',
            'Event Type',
            'Starts At',
            'Ends At',
            'Status',
            'Assigned To',
            'Notes',
        ];
    }

    /**
     * @param  CourtEvent  $event
     */
    public function map($event): array
    {
        return [
            $event->matter?->reference_no,
            $event->matter?->client?->display_name ?: $event->matter?->client?->name,
            $event->court?->name ?: $event->court_name,
            $event->case_number,
            $event->judicialOfficer?->name ?: $event->judicial_officer,
            $event->event_type,
            optional($event->starts_at)->format('Y-m-d H:i'),
            optional($event->ends_at)->format('Y-m-d H:i'),
            $event->status,
            $event->assignee?->name,
            $event->notes,
        ];
    }

    public function title(): string
    {
        return 'Litigation';
    }
}
