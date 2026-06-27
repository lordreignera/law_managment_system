<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CourtEvent;
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

        $myRecoveries = RecoveryAccount::with('client')
            ->where('assigned_to', $user->id)
            ->where('status', 'active')
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard', [
            'stats' => [
                'Open Matters' => Matter::whereNotIn('status', ['closed', 'archived'])->count(),
                'Clients' => Client::count(),
                'Recovery Accounts' => RecoveryAccount::where('status', 'active')->count(),
                'Pending Securities' => LandTitle::where('status', 'pending')->count(),
                'Court Events' => CourtEvent::whereDate('starts_at', '>=', now())->count(),
                'Draft Invoices' => Invoice::where('status', 'draft')->count(),
                'Requisitions' => Requisition::whereIn('status', ['draft', 'submitted'])->count(),
            ],
            'myRecoveries' => $myRecoveries,
        ]);
    }
}
