<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\StaffProfile;
use App\Models\User;

class HrController extends Controller
{
    public function dashboard()
    {
        $today = today();

        $stats = [
            [
                'label' => 'Active Staff',
                'value' => StaffProfile::where('employment_status', 'active')->count(),
                'icon' => 'mdi-account-check-outline',
            ],
            [
                'label' => 'Pending Access',
                'value' => StaffProfile::where('employment_status', 'pending')->count(),
                'icon' => 'mdi-account-clock-outline',
            ],
            [
                'label' => 'On Leave Today',
                'value' => LeaveRequest::where('status', 'approved')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->count(),
                'icon' => 'mdi-calendar-account-outline',
            ],
            [
                'label' => 'Pending Leave',
                'value' => LeaveRequest::where('status', 'submitted')->count(),
                'icon' => 'mdi-clipboard-clock-outline',
            ],
        ];

        $departmentHeadcount = Department::query()
            ->withCount([
                'users as active_staff_count' => fn ($query) => $query->whereHas(
                    'staffProfile',
                    fn ($query) => $query->where('employment_status', 'active')
                ),
            ])
            ->orderBy('name')
            ->get();

        $branchHeadcount = Branch::query()
            ->withCount([
                'users as active_staff_count' => fn ($query) => $query->whereHas(
                    'staffProfile',
                    fn ($query) => $query->where('employment_status', 'active')
                ),
            ])
            ->orderBy('name')
            ->get();

        $recentStaff = User::with(['staffProfile', 'branch', 'department', 'roles'])
            ->whereHas('staffProfile')
            ->latest()
            ->limit(8)
            ->get();

        $upcomingLeave = LeaveRequest::with(['user', 'leaveType'])
            ->whereIn('status', ['submitted', 'approved'])
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->limit(8)
            ->get();

        return view('modules.hr.dashboard', [
            'stats' => $stats,
            'departmentHeadcount' => $departmentHeadcount,
            'branchHeadcount' => $branchHeadcount,
            'recentStaff' => $recentStaff,
            'upcomingLeave' => $upcomingLeave,
        ]);
    }
}
