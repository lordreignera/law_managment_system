<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\CourtEvent;
use App\Models\File;
use App\Models\Invoice;
use App\Models\LandTitle;
use App\Models\Matter;
use App\Models\RecoveryAccount;
use App\Models\Requisition;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();

        if ($user->hasRole('Litigation Officer') && $user->can('litigation.dashboard')) {
            return redirect()->route('litigation.dashboard');
        }

        if ($user->hasRole('Accountant') && $user->can('finance.dashboard')) {
            return redirect()->route('finance.dashboard');
        }

        if ($user->hasRole('HR Manager') && $user->can('hr.dashboard')) {
            return redirect()->route('hr.dashboard');
        }

        $can = fn (string $permission) => $user->can($permission);
        $link = fn (string $permission, string $route, array $params = []) => $can($permission) ? route($route, $params) : null;

        $invoiceOutstanding = (float) Invoice::sum('total') - (float) Invoice::sum('paid_amount');

        $stats = [
            ['label' => 'Open Matters', 'value' => number_format(Matter::whereNotIn('status', ['closed', 'archived'])->count()), 'icon' => 'mdi-briefcase-outline', 'route' => $link('matters.index', 'matters.index')],
            ['label' => 'Active Files', 'value' => number_format(File::count()), 'icon' => 'mdi-folder-multiple-outline', 'route' => null],
            ['label' => 'Clients', 'value' => number_format(Client::count()), 'icon' => 'mdi-account-group-outline', 'route' => $link('clients.index', 'clients.index')],
            ['label' => 'Pending Intakes', 'value' => number_format(ClientIntake::where('status', 'pending_review')->count()), 'icon' => 'mdi-account-plus-outline', 'route' => $link('intakes.index', 'intakes.index')],
            ['label' => 'Active Recoveries', 'value' => number_format(RecoveryAccount::where('status', 'active')->count()), 'icon' => 'mdi-bank-outline', 'route' => $link('recoveries.index', 'recoveries.index')],
            ['label' => 'Recovery Outstanding', 'value' => number_format(RecoveryAccount::where('status', 'active')->sum('outstanding_amount')), 'icon' => 'mdi-cash-remove', 'route' => $link('recoveries.index', 'recoveries.index')],
            ['label' => 'Pending Securities', 'value' => number_format(LandTitle::where('status', 'pending')->count()), 'icon' => 'mdi-file-document-outline', 'route' => $link('land-titles.index', 'land-titles.index')],
            ['label' => 'Upcoming Court Events', 'value' => number_format(CourtEvent::whereDate('starts_at', '>=', today())->whereIn('status', ['scheduled', 'adjourned'])->count()), 'icon' => 'mdi-gavel', 'route' => $link('litigation.index', 'litigation.index')],
            ['label' => 'Outstanding Invoices', 'value' => number_format(max($invoiceOutstanding, 0)), 'icon' => 'mdi-receipt-text-outline', 'route' => $link('finance.index', 'finance.index')],
            ['label' => 'Pending Requisitions', 'value' => number_format(Requisition::whereIn('status', ['draft', 'submitted'])->count()), 'icon' => 'mdi-clipboard-text-outline', 'route' => $link('requisitions.index', 'requisitions.index')],
        ];

        $myRecoveries = RecoveryAccount::with('client')
            ->where('assigned_to', $user->id)
            ->where('status', 'active')
            ->latest()
            ->limit(8)
            ->get();

        $upcomingEvents = $can('litigation.index')
            ? CourtEvent::with(['matter', 'assignee'])
                ->whereDate('starts_at', '>=', today())
                ->whereIn('status', ['scheduled', 'adjourned'])
                ->orderBy('starts_at')
                ->limit(6)
                ->get()
            : collect();

        return view('dashboard', [
            'stats' => $stats,
            'myRecoveries' => $myRecoveries,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}
