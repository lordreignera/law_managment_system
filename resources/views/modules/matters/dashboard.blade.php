@extends('layouts.admin')

@section('title', 'Matter Dashboard')
@section('page-title', 'Matter Dashboard')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-dashboard-hero kfms-matter-dashboard-hero">
        <div>
            <span>Matter control room</span>
            <h2>Open, track, and move client matters through firm work.</h2>
            <p>Advocates monitor file status, responsible teams, practice areas, billing readiness, and recent matter activity from one place.</p>
        </div>
        <div class="kfms-dashboard-hero-actions">
            @can('matters.index')
                <a class="kfms-link-btn" href="{{ route('matters.index') }}">
                    <i class="mdi mdi-format-list-bulleted"></i>
                    Register
                </a>
            @endcan
            @can('matters.create')
                <a class="kfms-btn" href="{{ route('matters.create') }}">
                    <i class="mdi mdi-briefcase-plus-outline"></i>
                    New Matter
                </a>
            @endcan
            @can('intakes.create')
                <a class="kfms-link-btn" href="{{ route('intakes.create') }}">
                    <i class="mdi mdi-account-plus-outline"></i>
                    Walk-in Intake
                </a>
            @endcan
        </div>
    </section>

    <div class="kfms-stat-grid kfms-dashboard-kpis">
        @foreach ($stats as $stat)
            @can('matters.index')
                <a class="kfms-card kfms-stat-card" href="{{ $stat['route'] }}">
                    <span class="kfms-stat-icon"><i class="mdi {{ $stat['icon'] }}"></i></span>
                    <span class="kfms-stat-body">
                        <span class="kfms-card-label">{{ $stat['label'] }}</span>
                        <strong class="kfms-stat">{{ number_format($stat['value']) }}</strong>
                    </span>
                </a>
            @else
                <section class="kfms-card kfms-stat-card">
                    <span class="kfms-stat-icon"><i class="mdi {{ $stat['icon'] }}"></i></span>
                    <span class="kfms-stat-body">
                        <span class="kfms-card-label">{{ $stat['label'] }}</span>
                        <strong class="kfms-stat">{{ number_format($stat['value']) }}</strong>
                    </span>
                </section>
            @endcan
        @endforeach
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Matter Pipeline</h2>
                    <span>Current status movement across firm matters</span>
                </div>
                @can('matters.export')
                    <a class="kfms-link-btn" href="{{ route('matters.export') }}"><i class="mdi mdi-download"></i> Export</a>
                @endcan
            </div>
            <div class="kfms-dashboard-bar-list">
                @php($maxStatus = max($statusRows->max('count') ?: 0, 1))
                @foreach ($statusRows as $row)
                    <a class="kfms-bar-row" href="{{ $row['route'] }}">
                        <span>{{ $row['label'] }}</span>
                        <div><i style="width: {{ max(8, round(($row['count'] / $maxStatus) * 100)) }}%"></i></div>
                        <strong>{{ number_format($row['count']) }}</strong>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Practice Areas</h2>
                    <span>Active matters by work category</span>
                </div>
            </div>
            <div class="kfms-bordered-list">
                @forelse ($practiceAreaRows as $area)
                    <div>
                        <span>{{ $area->name }}</span>
                        <strong>{{ number_format($area->active_matters_count) }}</strong>
                    </div>
                @empty
                    <p class="kfms-empty">No practice areas have active matters yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>My Matter Work</h2>
                    <span>Matters opened by or assigned to you</span>
                </div>
                @can('matters.index')
                    <a class="kfms-link-btn" href="{{ route('matters.index') }}">View all <i class="mdi mdi-arrow-right"></i></a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Client</th>
                            <th>Practice Area</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($myMatters as $matter)
                            <tr>
                                <td>
                                    <strong>{{ $matter->reference_no }}</strong><br>
                                    <span>{{ \Illuminate\Support\Str::limit($matter->title, 34) }}</span>
                                </td>
                                <td>{{ $matter->client?->name ?: '-' }}</td>
                                <td>{{ $matter->practiceArea?->name ?: '-' }}</td>
                                <td>{{ $matter->statusLabel() }}</td>
                                <td><a class="kfms-link-btn" href="{{ route('matters.show', $matter) }}">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="kfms-empty">No assigned matters yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Matters</h2>
                    <span>Latest matter records opened or updated</span>
                </div>
                @can('matters.index')
                    <a class="kfms-link-btn" href="{{ route('matters.index') }}">Register <i class="mdi mdi-arrow-right"></i></a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Title</th>
                            <th>Client</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentMatters as $matter)
                            <tr>
                                <td><a href="{{ route('matters.show', $matter) }}">{{ $matter->reference_no }}</a></td>
                                <td>{{ \Illuminate\Support\Str::limit($matter->title, 42) }}</td>
                                <td>{{ $matter->client?->name ?: '-' }}</td>
                                <td>{{ $matter->statusLabel() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="kfms-empty">No matters recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
