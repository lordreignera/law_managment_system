@extends('layouts.admin')

@section('title', 'Recovery Reviews')
@section('page-title', 'Recovery Reviews')

@section('content')
    @if (session('status')) <div class="kfms-alert">{{ session('status') }}</div> @endif
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div><h2>Payments &amp; Closure Requests</h2><span>Check the bank or lender record and enter its reference before collections or settlements change the account balance.</span></div>
            <a class="kfms-link-btn" href="{{ route('recoveries.dashboard') }}">Back to Dashboard</a>
        </div>
        @error('decision') <div class="kfms-alert">{{ $message }}</div> @enderror
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Submitted</th><th>Client / Account</th><th>Officer</th><th>Request</th><th>Amount</th><th>Evidence</th><th>Decision</th></tr></thead>
                <tbody>
                    @forelse ($activities as $activity)
                        <tr>
                            <td>{{ $activity->created_at?->format('d M Y, H:i') }}</td>
                            <td><a href="{{ route('recoveries.show', $activity->account) }}">{{ $activity->account?->debtor_name }}</a><br><small>{{ $activity->account?->client?->name }}</small></td>
                            <td>{{ $activity->user?->name ?: '-' }}</td>
                            <td>{{ $activity->outcomeLabel() }}<br><small>{{ $activity->notes }}</small></td>
                            <td>{{ $activity->response_outcome === 'paid' ? ($activity->account?->currency ?: 'UGX').' '.number_format((float) $activity->amount_paid, 2) : '-' }}</td>
                            <td>@forelse ($activity->attachments as $evidence)<a href="{{ route('attachments.download', $evidence) }}">{{ $evidence->original_name }}</a>@empty - @endforelse</td>
                            <td>
                                @can('recoveries.reviews.decision')
                                    <form method="POST" action="{{ route('recoveries.reviews.decision', $activity) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <label for="approve-reference-{{ $activity->id }}">Verification reference / approval basis</label>
                                        <input id="approve-reference-{{ $activity->id }}" name="review_notes" type="text" minlength="5" maxlength="2000" placeholder="Bank statement or lender confirmation reference" required>
                                        <button class="kfms-btn" type="submit">Approve</button>
                                    </form>
                                    <details class="kfms-recovery-reject">
                                        <summary>Reject</summary>
                                        <form method="POST" action="{{ route('recoveries.reviews.decision', $activity) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject">
                                            <label for="reject-reason-{{ $activity->id }}">Reason</label>
                                            <textarea id="reject-reason-{{ $activity->id }}" name="review_notes" rows="2" maxlength="2000" required></textarea>
                                            <button class="kfms-link-btn" type="submit">Confirm Rejection</button>
                                        </form>
                                    </details>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="kfms-empty">No payments or closures are waiting for review.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $activities->links() }}
    </section>
@endsection
