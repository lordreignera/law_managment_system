@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <section class="kfms-dashboard-hero">
        <div>
            <span>{{ now()->format('l, d M Y') }}</span>
            <h2>Firm operations at a glance</h2>
            <p>Track active work, approvals, court activity, securities, recoveries, and internal communication from one place.</p>
        </div>
        <div class="kfms-dashboard-hero-actions">
            @can('messages.index')
                <a class="kfms-link-btn" href="{{ route('messages.index') }}"><i class="mdi mdi-chat-outline"></i> Messages</a>
            @endcan
            @can('intakes.create')
                <a class="kfms-btn" href="{{ route('intakes.create') }}"><i class="mdi mdi-account-plus-outline"></i> New Intake</a>
            @endcan
        </div>
    </section>

    <div class="kfms-stat-grid kfms-kpi-grid kfms-dashboard-kpis">
        @foreach (collect($stats)->take(8) as $stat)
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
        <section class="kfms-panel kfms-dashboard-chart-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Operations Snapshot</h2>
                    <span>Live count by active work area</span>
                </div>
                <span>{{ $companySetting->short_name }}</span>
            </div>
            <div class="kfms-bar-chart">
                @foreach ($chartItems as $item)
                    <div class="kfms-bar-row">
                        <div class="kfms-bar-label">
                            <i class="mdi {{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                        </div>
                        <div class="kfms-bar-track">
                            <span style="width: {{ $item['percent'] }}%"></span>
                        </div>
                        <strong>{{ number_format($item['value']) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="kfms-panel kfms-dashboard-messages">
            <div class="kfms-panel-header">
                <div>
                    <h2>Messages</h2>
                    <span>Recent internal communication</span>
                </div>
                @can('messages.index')
                    <a class="kfms-link-btn" href="{{ route('messages.index') }}">Open <i class="mdi mdi-arrow-right"></i></a>
                @endcan
            </div>
            <div class="kfms-dashboard-message-list">
                @forelse ($recentConversations as $conversation)
                    <a href="{{ route('messages.show', $conversation) }}" class="{{ $conversation->unread_for_user ? 'is-unread' : '' }}">
                        <span class="kfms-dashboard-message-avatar">{{ str($conversation->title)->substr(0, 2)->upper() }}</span>
                        <span>
                            <strong>{{ $conversation->title }}</strong>
                            <em>{{ $conversation->latestMessage?->sender?->name ?: 'System' }}: {{ str($conversation->latestMessage?->body ?: 'No messages yet')->limit(68) }}</em>
                        </span>
                        <time>{{ $conversation->last_message_at?->diffForHumans() ?: 'New' }}</time>
                    </a>
                @empty
                    <div class="kfms-empty-state">
                        <i class="mdi mdi-chat-outline"></i>
                        <strong>No recent messages</strong>
                        <span>Your latest firm conversations will appear here.</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="kfms-grid-two">
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
            @if (isset($upcomingEvents) && $upcomingEvents->isNotEmpty())
                <div class="kfms-dashboard-event-list">
                    @foreach ($upcomingEvents as $event)
                        <a href="{{ route('litigation.index') }}">
                            <span>
                                <strong>{{ $event->starts_at?->format('d M') }}</strong>
                                <em>{{ $event->starts_at?->format('H:i') }}</em>
                            </span>
                            <span>
                                <strong>{{ $event->matter?->reference_no ?: 'Court event' }}</strong>
                                <em>{{ $event->court_name ?: 'Court not specified' }} · {{ $event->assignee?->name ?: 'Unassigned' }}</em>
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="kfms-empty-state">
                    <i class="mdi mdi-calendar-check-outline"></i>
                    <strong>No upcoming court events</strong>
                    <span>Scheduled hearings and mentions will appear here.</span>
                </div>
            @endif
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
