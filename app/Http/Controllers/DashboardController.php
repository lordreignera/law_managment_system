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
        return view('dashboard', [
            'stats' => [
                'Open Matters' => Matter::where('status', 'open')->count(),
                'Clients' => Client::count(),
                'Recovery Accounts' => RecoveryAccount::where('status', 'active')->count(),
                'Pending Titles' => LandTitle::where('status', 'pending')->count(),
                'Court Events' => CourtEvent::whereDate('starts_at', '>=', now())->count(),
                'Draft Invoices' => Invoice::where('status', 'draft')->count(),
                'Requisitions' => Requisition::whereIn('status', ['draft', 'submitted'])->count(),
            ],
        ]);
    }
}
