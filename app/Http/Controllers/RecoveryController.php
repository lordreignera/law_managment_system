<?php

namespace App\Http\Controllers;

use App\Models\RecoveryAccount;

class RecoveryController extends Controller
{
    public function index()
    {
        return view('modules.recoveries.index', [
            'accounts' => RecoveryAccount::with('client')->latest()->paginate(15),
        ]);
    }
}
