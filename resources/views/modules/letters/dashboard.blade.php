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
        </div>

        <div class="kfms-workflow-actions">
            <a href="{{ route('letters.create', ['letter_type' => 'general']) }}">
                <i class="mdi mdi-email-edit-outline"></i>
                <strong>Draft Letter</strong>
                <span>Use firm branding, client details, and a reusable template.</span>
            </a>
            <a href="{{ route('letters.create', ['letter_type' => 'opinion']) }}">
                <i class="mdi mdi-scale-balance"></i>
                <strong>Legal Opinion</strong>
                <span>Prepare structured advice after instructions or consultation.</span>
            </a>
            <a href="{{ route('letters.create', ['letter_type' => 'demand_notice']) }}">
                <i class="mdi mdi-file-alert-outline"></i>
                <strong>Demand Notice</strong>
                <span>Generate pre-litigation and recovery notices with references.</span>
            </a>
            <a href="{{ route('letters.index', ['status' => 'pending_review']) }}">
                <i class="mdi mdi-check-decagram-outline"></i>
                <strong>Review Queue</strong>
                <span>Approve letters before sending them to recipients or clients.</span>
            </a>
        </div>
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Letters</h2>
                    <span>Latest drafts and outgoing correspondence</span>
                </div>
                <a class="kfms-link-btn" href="{{ route('letters.index') }}">View all</a>
            </div>
            @include('modules.letters.partials.table', ['letters' => $recentLetters])
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Pending Review</h2>
                    <span>Letters awaiting approval</span>
                </div>
                <a class="kfms-link-btn" href="{{ route('letters.index', ['status' => 'pending_review']) }}">Open queue</a>
            </div>
            @include('modules.letters.partials.table', ['letters' => $pendingLetters])
        </section>
    </div>
@endsection
