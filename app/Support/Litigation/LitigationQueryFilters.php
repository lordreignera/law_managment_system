<?php

namespace App\Support\Litigation;

use Illuminate\Database\Eloquent\Builder;

class LitigationQueryFilters
{
    public const STAGES = [
        'court_process' => [
            'label' => 'Filing, Service & Court Process',
            'event_types' => ['mention', 'hearing', 'conference', 'filing_deadline', 'other'],
            'statuses' => ['scheduled', 'adjourned'],
        ],
        'judgment_ruling' => [
            'label' => 'Judgment / Ruling',
            'event_types' => ['judgment', 'ruling'],
        ],
        'taxation_execution' => [
            'label' => 'Taxation & Execution',
            'event_types' => ['taxation', 'execution', 'garnishee', 'attachment', 'committal'],
        ],
    ];

    public static function apply(Builder $query, array $filters, ?int $currentUserId = null): Builder
    {
        return $query
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = (string) $filters['search'];

                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('case_number', 'like', "%{$search}%")
                        ->orWhere('court_name', 'like', "%{$search}%")
                        ->orWhere('judicial_officer', 'like', "%{$search}%")
                        ->orWhereHas('assignee', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('matter', fn (Builder $query) => $query
                            ->where('reference_no', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%"));
                });
            })
            ->when(! empty($filters['stage']) && isset(self::STAGES[$filters['stage']]), function (Builder $query) use ($filters) {
                $stage = self::STAGES[$filters['stage']];

                $query->whereIn('event_type', $stage['event_types']);

                if (! empty($stage['statuses'])) {
                    $query->whereIn('status', $stage['statuses']);
                }
            })
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['event_type']), fn (Builder $query) => $query->where('event_type', $filters['event_type']))
            ->when(! empty($filters['assigned_to']), fn (Builder $query) => $query->where('assigned_to', (int) $filters['assigned_to']))
            ->when(! empty($filters['mine']) && $currentUserId, fn (Builder $query) => $query->where('assigned_to', $currentUserId));
    }
}
