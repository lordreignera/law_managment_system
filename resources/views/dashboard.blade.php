@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($stats as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ number_format($value) }}</strong>
            </section>
        @endforeach
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Today</h2>
                <span>{{ now()->format('d M Y') }}</span>
            </div>
            <div class="kfms-action-list">
                <a href="{{ route('matters.index') }}">Review open matters</a>
                @can('recoveries.index')
                    <a href="{{ route('recoveries.index') }}">Check recovery assignments</a>
                @elsecan('recoveries.mine')
                    <a href="{{ route('recoveries.mine') }}">View my recoveries</a>
                @endcan
                <a href="{{ route('land-titles.index') }}">Track pending securities</a>
                <a href="{{ route('finance.index') }}">Approve requisitions and invoices</a>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Workflow Map</h2>
                <span>KFMS</span>
            </div>
            <ol class="kfms-steps">
                <li>Instruction intake</li>
                <li>Client, matter, recovery, or title registration</li>
                <li>Assignment to responsible officer</li>
                <li>Updates, documents, diary, and approvals</li>
                <li>Billing, reporting, and audit trail</li>
            </ol>
        </section>
    </div>

    @if (isset($myRecoveries) && $myRecoveries->isNotEmpty())
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>My Recovery Assignments</h2>
                    <span>Active accounts assigned to you</span>
                </div>
                @can('recoveries.mine')
                    <a class="kfms-link-btn" href="{{ route('recoveries.mine') }}">View all <i class="mdi mdi-arrow-right"></i></a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr><th>Debtor</th><th>Bank/Client</th><th>Account</th><th>Outstanding</th><th>Recovered</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($myRecoveries as $account)
                            <tr>
                                <td><a href="{{ route('recoveries.show', $account) }}">{{ $account->debtor_name }}</a></td>
                                <td>{{ $account->client?->name ?: '-' }}</td>
                                <td>{{ $account->account_number ?: '-' }}</td>
                                <td>{{ number_format($account->outstanding_amount) }}</td>
                                <td>{{ number_format($account->amount_recovered) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
