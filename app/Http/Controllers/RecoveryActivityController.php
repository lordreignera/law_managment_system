<?php

namespace App\Http\Controllers;

use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecoveryActivityController extends Controller
{
    /**
     * Log a demand / follow-up. When the officer records money collected the
     * account's recovered total is recomputed from all activity payments.
     */
    public function store(Request $request, RecoveryAccount $recovery)
    {
        $request->merge([
            'amount_paid' => $this->normalizeMoney($request->input('amount_paid')),
            'promised_amount' => $this->normalizeMoney($request->input('promised_amount')),
        ]);

        $data = $request->validate([
            'activity_type' => ['required', 'in:'.implode(',', array_keys(RecoveryActivity::TYPES))],
            'response_outcome' => ['required', 'in:'.implode(',', array_keys(RecoveryActivity::OUTCOMES))],
            'activity_at' => ['required', 'date'],
            'amount_paid' => [Rule::requiredIf($request->input('response_outcome') === 'paid'), 'nullable', 'numeric', 'min:0.01'],
            'promised_amount' => [Rule::requiredIf($request->input('response_outcome') === 'promised'), 'nullable', 'numeric', 'min:0.01'],
            'promised_on' => [Rule::requiredIf($request->input('response_outcome') === 'promised'), 'nullable', 'date'],
            'receipt' => [Rule::requiredIf($request->input('response_outcome') === 'paid'), 'nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        if ($data['response_outcome'] === 'paid') {
            $data['promised_amount'] = null;
            $data['promised_on'] = null;
        } elseif ($data['response_outcome'] === 'promised') {
            $data['amount_paid'] = null;
        } else {
            $data['amount_paid'] = null;
            $data['promised_amount'] = null;
            $data['promised_on'] = null;
        }

        $amountPaid = (float) ($data['amount_paid'] ?? 0);
        $netOutstanding = $recovery->net_outstanding_balance;

        if ($amountPaid > 0 && $amountPaid > $netOutstanding) {
            throw ValidationException::withMessages([
                'amount_paid' => 'The amount paid cannot exceed the current net outstanding balance.',
            ]);
        }

        $data['user_id'] = $request->user()->id;
        unset($data['receipt']);

        DB::transaction(function () use ($data, $request, $recovery) {
            $activity = $recovery->activities()->create($data);

            if ($request->hasFile('receipt')) {
                $activity->addAttachment($request->file('receipt'), [
                    'category' => 'recovery-payment-receipt',
                    'title' => 'Payment receipt',
                ]);
            }

            $recovery->recomputeRecovered();
        });

        $route = $request->input('redirect_to') === 'mine' ? 'recoveries.mine' : 'recoveries.show';

        return $route === 'recoveries.mine'
            ? redirect()->route($route)->with('status', 'Follow-up logged.')
            : redirect()->route($route, $recovery)->with('status', 'Follow-up logged.');
    }

    private function normalizeMoney(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_replace([',', ' '], '', (string) $value);
    }
}
