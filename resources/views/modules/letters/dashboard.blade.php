@extends('layouts.admin')

@section('title', 'Letters & Opinions')
@section('page-title', 'Letters & Opinions')

@section('content')
    <section class="kfms-dashboard-hero kfms-letters-dashboard-hero">
        <div>
            <span>Letters & opinions control room</span>
            <h2>Draft, review, approve, and dispatch firm correspondence.</h2>
            <p>Teams manage branded letters, opinions, demand notices, templates, approvals, sent records, and received copies from one place.</p>
        </div>
        <div class="kfms-dashboard-hero-actions">
            @can('letters.create')
                <a class="kfms-btn" href="{{ route('letters.create') }}">
                    <i class="mdi mdi-plus"></i>
                    Create Letter
                </a>
            @endcan
            @can('letters.index')
                <a class="kfms-link-btn" href="{{ route('letters.index') }}">
                    <i class="mdi mdi-format-list-bulleted"></i>
                    All Letters
                </a>
            @endcan
            @can('letters.templates.index')
                <a class="kfms-link-btn" href="{{ route('letters.templates.index') }}">
                    <i class="mdi mdi-file-cog-outline"></i>
                    Templates
                </a>
            @endcan
        </div>
    </section>

    @php
        $summaryIcons = [
            'Drafts' => 'mdi-email-edit-outline',
            'Pending Review' => 'mdi-clipboard-clock-outline',
            'Sent' => 'mdi-send-check-outline',
            'Received Copies' => 'mdi-file-check-outline',
        ];
    @endphp

    <div class="kfms-stat-grid kfms-dashboard-kpis">
        @foreach ($summary as $label => $value)
            <section class="kfms-card kfms-stat-card">
                <span class="kfms-stat-icon"><i class="mdi {{ $summaryIcons[$label] ?? 'mdi-file-document-outline' }}"></i></span>
                <span class="kfms-stat-body">
                    <span class="kfms-card-label">{{ $label }}</span>
                    <strong class="kfms-stat">{{ number_format($value) }}</strong>
                </span>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Document Workspace</h2>
                <span>Create branded letters, opinions, notices, and client correspondence.</span>
            </div>
            @can('letters.create')
                <a class="kfms-btn" href="{{ route('letters.create') }}">
                    <i class="mdi mdi-file-document-edit-outline"></i>
                    Open Document Workspace
                </a>
            @endcan
        </div>
    </section>

    <div class="kfms-letter-dashboard-stack">
        <section class="kfms-panel" data-letter-dashboard-section="pending-review">
            <div class="kfms-panel-header">
                <div>
                    <h2>Pending Review</h2>
                    <span>Letters awaiting approval</span>
                </div>
                <a class="kfms-link-btn" href="{{ route('letters.index', ['status' => 'pending_review']) }}">Open queue</a>
            </div>
            @include('modules.letters.partials.table', ['letters' => $pendingLetters])
        </section>

        <section class="kfms-panel" data-letter-dashboard-section="recent">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Letters</h2>
                    <span>Latest drafts and outgoing correspondence</span>
                </div>
                <a class="kfms-link-btn" href="{{ route('letters.index') }}">View all</a>
            </div>
            @include('modules.letters.partials.table', ['letters' => $recentLetters])
        </section>
    </div>
@endsection
