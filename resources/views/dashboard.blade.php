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
                <a href="{{ route('recoveries.index') }}">Check recovery assignments</a>
                <a href="{{ route('land-titles.index') }}">Track pending land titles</a>
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
@endsection
