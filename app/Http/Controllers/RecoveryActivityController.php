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
    /** Log follow-up immediately; financial and closure outcomes await manager review. */
    public function store(Request $request, RecoveryAccount $recovery)
    {
        abort_unless(RecoveryAccount::forBranchOf($request->user())->whereKey($recovery->id)->exists(), 403);

        if ($request->user()->hasRole('Recovery Officer') && $recovery->assigned_to !== $request->user()->id) {
            abort(403, 'Only the assigned recovery officer can log activity for this account.');
        }

        abort_if($recovery->status !== 'active' || $recovery->settled_externally_at, 422, 'This recovery account is closed and cannot receive another activity.');
        if ($request->input('response_outcome') === 'write_off_requested') {
            abort_unless($request->user()->can('recoveries.reviews.index'), 403);
        }

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
            'next_follow_up_at' => ['nullable', 'date'],
            'receipt' => [Rule::requiredIf(in_array($request->input('response_outcome'), ['paid', 'fully_repaid', 'write_off_requested'], true)), 'nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
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

        if ($data['response_outcome'] !== 'promised') {
            $data['next_follow_up_at'] = $data['next_follow_up_at'] ?? null;
        }

        $amountPaid = (float) ($data['amount_paid'] ?? 0);
        $netOutstanding = $recovery->net_outstanding_balance;

        if ($amountPaid > 0 && $amountPaid > $netOutstanding) {
            throw ValidationException::withMessages([
                'amount_paid' => 'The amount paid cannot exceed the current net outstanding balance.',
            ]);
        }

        $data['user_id'] = $request->user()->id;
        $needsReview = in_array($data['response_outcome'], ['paid', 'fully_repaid', 'write_off_requested'], true);
        $data['review_status'] = $needsReview ? 'pending' : 'approved';
        unset($data['receipt']);

        DB::transaction(function () use ($data, $request, $recovery, $needsReview) {
            $activity = $recovery->activities()->create($data);

            if ($request->hasFile('receipt')) {
                $activity->addAttachment($request->file('receipt'), [
                    'category' => $data['response_outcome'] === 'paid' ? 'recovery-payment-receipt' : 'recovery-closure-evidence',
                    'title' => $data['response_outcome'] === 'paid' ? 'Payment receipt' : 'Closure evidence',
                ]);
            }

            $recovery->forceFill($needsReview ? [
                'last_contact_at' => $data['activity_at'],
            ] : [
                'last_contact_at' => $data['activity_at'],
                'next_follow_up_at' => $data['next_follow_up_at'] ?? ($data['promised_on'] ?? null),
                'pipeline_stage' => match ($data['response_outcome']) {
                    'promised' => 'promise_to_pay',
                    'needs_follow_up' => 'contact_pending',
                    'disputed' => 'legal_review',
                    default => 'engaged',
                },
            ])->save();
        });

        $route = $request->input('redirect_to') === 'mine' ? 'recoveries.mine' : 'recoveries.show';

        return $route === 'recoveries.mine'
            ? redirect()->route($route)->with('status', $needsReview ? 'Submitted for manager review.' : 'Follow-up logged.')
            : redirect()->route($route, $recovery)->with('status', $needsReview ? 'Submitted for manager review.' : 'Follow-up logged.');
    }

    private function normalizeMoney(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_replace([',', ' '], '', (string) $value);
    }
}
