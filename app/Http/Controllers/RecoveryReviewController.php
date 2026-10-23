<?php

namespace App\Http\Controllers;

use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecoveryReviewController extends Controller
{
    private const REVIEWABLE = ['paid', 'fully_repaid', 'write_off_requested'];

    public function index(Request $request)
    {
        return view('modules.recoveries.reviews', [
            'activities' => RecoveryActivity::with(['account.client', 'account.assignee', 'user', 'attachments'])
                ->where('review_status', 'pending')
                ->whereIn('response_outcome', self::REVIEWABLE)
                ->whereHas('account', fn ($query) => $query->forBranchOf($request->user()))
                ->oldest()
                ->paginate(20),
        ]);
    }

    public function decision(Request $request, RecoveryActivity $activity)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'review_notes' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $activity, $data) {
            $account = RecoveryAccount::forBranchOf($request->user())
                ->whereKey($activity->recovery_account_id)->lockForUpdate()->first();
            abort_unless($account, 403);
            $locked = RecoveryActivity::whereKey($activity->id)
                ->where('recovery_account_id', $account->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->response_outcome, self::REVIEWABLE, true) && $locked->review_status === 'pending', 409);

            if ($data['decision'] === 'approve') {
                abort_unless($account->status === 'active' && ! $account->settled_externally_at, 409);
                abort_unless($locked->attachments()->exists(), 422, 'Supporting evidence is required before approval.');
                if ($locked->response_outcome === 'paid' && (float) $locked->amount_paid > $account->net_outstanding_balance) {
                    throw ValidationException::withMessages(['decision' => 'This payment exceeds the current outstanding balance. Reject it and request a corrected entry.']);
                }
            }

            $locked->update([
                'review_status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);

            if ($data['decision'] !== 'approve') {
                return;
            }

            $account->recomputeRecovered();
            $account->refresh();
            if ($locked->response_outcome === 'fully_repaid') {
                $account->update([
                    'status' => 'closed', 'pipeline_stage' => 'closed',
                    'settled_externally_at' => now(), 'next_follow_up_at' => null,
                ]);
            } elseif ($locked->response_outcome === 'write_off_requested') {
                $account->update([
                    'status' => 'written_off', 'pipeline_stage' => 'closed', 'next_follow_up_at' => null,
                ]);
            } elseif ($account->net_outstanding_balance <= 0) {
                $account->update([
                    'status' => 'closed', 'pipeline_stage' => 'recovered', 'next_follow_up_at' => null,
                ]);
            }
        });

        return back()->with('status', $data['decision'] === 'approve' ? 'Recovery evidence approved.' : 'Recovery entry rejected. The officer can submit a corrected entry.');
    }

    public function reverse(Request $request, RecoveryActivity $activity)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        DB::transaction(function () use ($request, $activity, $data) {
            $account = RecoveryAccount::forBranchOf($request->user())
                ->whereKey($activity->recovery_account_id)->lockForUpdate()->first();
            abort_unless($account, 403);
            $locked = RecoveryActivity::whereKey($activity->id)
                ->where('recovery_account_id', $account->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->response_outcome, self::REVIEWABLE, true) && $locked->review_status === 'approved', 409);

            $locked->update([
                'review_status' => 'reversed',
                'reversed_by' => $request->user()->id,
                'reversed_at' => now(),
                'reversal_reason' => $data['reason'],
            ]);
            $account->update(['settled_externally_at' => null]);
            $account->recomputeRecovered();
            $account->refresh();

            $otherClosure = $account->activities()->where('review_status', 'approved')
                ->whereIn('response_outcome', ['fully_repaid', 'write_off_requested'])->first();
            if ($otherClosure?->response_outcome === 'fully_repaid') {
                $account->update(['status' => 'closed', 'pipeline_stage' => 'closed', 'settled_externally_at' => now()]);
            } elseif ($otherClosure?->response_outcome === 'write_off_requested') {
                $account->update(['status' => 'written_off', 'pipeline_stage' => 'closed']);
            } elseif ($account->net_outstanding_balance <= 0) {
                $account->update(['status' => 'closed', 'pipeline_stage' => 'recovered']);
            } else {
                $account->update(['status' => 'active', 'pipeline_stage' => 'engaged']);
            }
        });

        return back()->with('status', 'Approval reversed. Balances and account status have been recalculated.');
    }
}
