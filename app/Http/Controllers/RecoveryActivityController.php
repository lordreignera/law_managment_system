<?php

namespace App\Http\Controllers;

use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use Illuminate\Http\Request;

class RecoveryActivityController extends Controller
{
    /**
     * Log a demand / follow-up. When the officer records money collected the
     * account's recovered total is recomputed from all activity payments.
     */
    public function store(Request $request, RecoveryAccount $recovery)
    {
        $data = $request->validate([
            'activity_type' => ['required', 'in:'.implode(',', array_keys(RecoveryActivity::TYPES))],
            'activity_at' => ['required', 'date'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'promised_amount' => ['nullable', 'numeric', 'min:0'],
            'promised_on' => ['nullable', 'date'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $data['user_id'] = $request->user()->id;

        $recovery->activities()->create($data);

        $recovery->recomputeRecovered();

        return redirect()
            ->route('recoveries.show', $recovery)
            ->with('status', 'Follow-up logged.');
    }
}
