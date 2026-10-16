<?php

namespace App\Http\Controllers;

use App\Models\PayrollComponent;
use App\Models\PayrollEmployeeProfile;
use App\Models\PayrollPeriod;
use App\Models\PayrollRule;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function dashboard(Request $request)
    {
        return view('modules.payroll.dashboard', [
            'stats' => [
                'Active Components' => PayrollComponent::where('is_active', true)->count(),
                'Calculation Rules' => PayrollRule::where('is_active', true)->count(),
                'Payroll Employees' => PayrollEmployeeProfile::where('is_active', true)->count(),
                'Open Periods' => PayrollPeriod::whereNotIn('status', ['closed', 'cancelled'])->count(),
            ],
            'periods' => PayrollPeriod::with(['run'])->latest('starts_on')->limit(8)->get(),
            'components' => PayrollComponent::where('is_active', true)->orderBy('sort_order')->orderBy('name')->limit(8)->get(),
        ]);
    }
}
