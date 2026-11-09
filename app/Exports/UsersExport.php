<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class UsersExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private array $filters = []) {}

    public function query()
    {
        return User::query()
            ->with(['branch', 'department', 'roles', 'staffProfile'])
            ->when($this->filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('roles', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($this->filters['status'] ?? null, fn ($query, $status) => $query->whereHas('staffProfile', fn ($query) => $query->where('employment_status', $status)))
            ->when($this->filters['role'] ?? null, fn ($query, $role) => $query->role($role))
            ->latest();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Staff No',
            'Branch',
            'Department',
            'Job Title',
            'Requested Role',
            'Roles',
            'Status',
            'Joined On',
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->staffProfile?->phone,
            $user->staffProfile?->staff_no,
            $user->branch?->name,
            $user->department?->name,
            $user->staffProfile?->job_title,
            $user->staffProfile?->requested_role,
            $user->roles->pluck('name')->join(', '),
            $user->staffProfile?->employment_status ?: 'active',
            optional($user->staffProfile?->joined_on)->format('Y-m-d'),
        ];
    }

    public function title(): string
    {
        return 'Users';
    }
}
