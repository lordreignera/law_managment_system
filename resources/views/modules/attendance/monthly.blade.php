@extends('layouts.admin')

@section('title', 'Monthly Attendance')
@section('page-title', 'Monthly Attendance')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Monthly attendance report</h2>
                <span>Weekends and configured public holidays are excluded from absence totals.</span>
            </div>
            <div class="kfms-toolbar-actions">
                <form class="kfms-calendar-jump" method="GET" action="{{ route('attendance.monthly') }}">
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" aria-label="Attendance month">
                    <button class="kfms-link-btn" type="submit">View month</button>
                </form>
                <a class="kfms-link-btn" href="{{ route('attendance.index') }}">Daily report</a>
            </div>
        </div>

        <div class="kfms-stat-grid">
            @foreach ($summary as $label => $value)
                <section class="kfms-card"><span class="kfms-card-label">{{ $label }}</span><strong class="kfms-stat">{{ $value }}</strong></section>
            @endforeach
        </div>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr><th>Staff</th><th>Department</th><th>Present</th><th>Late</th><th>Incomplete</th><th>On leave</th><th>Absent</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><strong>{{ $row['staffMember']->name }}</strong><br><small>{{ $row['staffMember']->staffProfile?->staff_no ?: $row['staffMember']->email }}</small></td>
                            <td>{{ $row['staffMember']->department?->name ?: '-' }}</td>
                            <td>{{ $row['totals']['present'] }}</td>
                            <td>{{ $row['totals']['late'] }}</td>
                            <td>{{ $row['totals']['incomplete'] }}</td>
                            <td>{{ $row['totals']['on_leave'] }}</td>
                            <td>{{ $row['totals']['absent'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="kfms-empty">No active staff found in this scope.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
