<?php

namespace App\Http\Controllers;

use App\Models\RecoveryAccount;
use Illuminate\Http\Request;

class RecoveryController extends Controller
{
    public function index(Request $request)
    {
        $accounts = RecoveryAccount::with('client')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('debtor_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.recoveries.index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
