@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="kfms-stat-grid kfms-kpi-grid">
        @foreach ($stats as $stat)
            @if ($stat['route'])
                <a class="kfms-card kfms-stat-card" href="{{ $stat['route'] }}">
                    <span class="kfms-stat-icon"><i class="mdi {{ $stat['icon'] }}"></i></span>
                    <span class="kfms-stat-body">
                        <span class="kfms-card-label">{{ $stat['label'] }}</span>
                        <strong class="kfms-stat">{{ $stat['value'] }}</strong>
                    </span>
                </a>
            @else
                <section class="kfms-card kfms-stat-card">
                    <span class="kfms-stat-icon"><i class="mdi {{ $stat['icon'] }}"></i></span>
                    <span class="kfms-stat-body">
                        <span class="kfms-card-label">{{ $stat['label'] }}</span>
                        <strong class="kfms-stat">{{ $stat['value'] }}</strong>
                    </span>
                </section>
            @endif
        @endforeach
    </div>

    <div class="kfms-grid-two">
        @if (isset($upcomingEvents) && $upcomingEvents->isNotEmpty())
            <section class="kfms-panel">
                <div class="kfms-panel-header">
                    <div>
                        <h2>Upcoming Court Events</h2>
                        <span>Next scheduled hearings and mentions</span>
                    </div>
                    @can('litigation.index')
                        <a class="kfms-link-btn" href="{{ route('litigation.index') }}">View all <i class="mdi mdi-arrow-right"></i></a>
                    @endcan
                </div>
                <div class="kfms-table-wrap">
                    <table class="kfms-table">
                        <thead>
                            <tr><th>Date</th><th>Matter</th><th>Court</th><th>Advocate</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($upcomingEvents as $event)
                                <tr>
                                    <td>{{ $event->starts_at?->format('d M, H:i') }}</td>
                                    <td>{{ $event->matter?->reference_no ?: '-' }}</td>
                                    <td>{{ $event->court_name ?: '-' }}</td>
                                    <td>{{ $event->assignee?->name ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
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
        @endif

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Quick Actions</h2>
                <span>{{ now()->format('d M Y') }}</span>
            </div>
            <div class="kfms-action-list">
                @can('intakes.create')
                    <a href="{{ route('intakes.create') }}">Record a new client intake</a>
                @endcan
                @can('matters.index')
                    <a href="{{ route('matters.index') }}">Review open matters</a>
                @endcan
                @can('recoveries.index')
                    <a href="{{ route('recoveries.index') }}">Check recovery assignments</a>
                @elsecan('recoveries.mine')
                    <a href="{{ route('recoveries.mine') }}">View my recoveries</a>
                @endcan
                @can('land-titles.index')
                    <a href="{{ route('land-titles.index') }}">Track pending securities</a>
                @endcan
                @can('finance.index')
                    <a href="{{ route('finance.index') }}">Approve requisitions and invoices</a>
                @endcan
            </div>
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
