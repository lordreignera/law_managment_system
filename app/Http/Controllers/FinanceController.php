<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Requisition;

class FinanceController extends Controller
{
    public function index()
    {
        return view('modules.finance.index', [
            'invoices' => Invoice::latest()->limit(10)->get(),
            'requisitions' => Requisition::latest()->limit(10)->get(),
        ]);
    }
}
