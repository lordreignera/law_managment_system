@extends('layouts.admin')

@section('title', 'ADR Resolution')
@section('page-title', 'Alternative Dispute Resolution (ADR)')

@section('content')
    @php
        $file = $adr->file;
        $matter = $adr->client?->matter;
        $courtRequired = $adr->response === 'court_required';
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $adr->adr_no }}</h2>
                <span>{{ $adr->title }} - {{ $adr->client?->display_name }}</span>
            </div>
            <div class="kfms-header-actions">
                <a class="kfms-link-btn" href="{{ route('clients.show', $adr->client) }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back to Client
                </a>
                @if (! $file)
                    <a class="kfms-btn" href="{{ route('clients.files.create', ['client' => $adr->client, 'adr' => $adr->id]) }}">
                        <i class="mdi mdi-folder-plus"></i>
                        Open File
                    </a>
                @elseif (! $matter)
                    <a class="kfms-btn" href="{{ route('clients.matters.create', $adr->client) }}">
                        <i class="mdi mdi-briefcase-plus"></i>
                        Open Matter
                    </a>
                @else
                    <a class="kfms-btn" href="{{ route('matters.show', $matter) }}">
                        <i class="mdi mdi-briefcase-eye"></i>
                        View Matter
                    </a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        @if ($courtRequired && ! $file)
            <div class="kfms-alert kfms-alert-warning">
                This ADR requires court action. Open a file to proceed to a matter.
            </div>
        @endif

        <div class="kfms-detail-grid">
            <div><span>Conflict Party</span><strong>{{ $adr->conflict_party_name }}</strong></div>
            <div><span>Contact</span><strong>{{ $adr->conflict_party_contact ?: '-' }}</strong></div>
            <div><span>Source Conflict Party</span><strong>{{ $adr->intakeConflictParty?->name ?: '-' }}</strong></div>
            <div><span>Method</span><strong>{{ $adr->method ? str($adr->method)->headline() : '-' }}</strong></div>
            <div><span>ADR Date</span><strong>{{ $adr->resolved_on?->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Response</span><strong>{{ str($adr->response)->headline() }}</strong></div>
            <div><span>Status</span><strong>{{ str($adr->status)->headline() }}</strong></div>
            <div><span>File</span><strong>{{ $file?->file_number ?: 'Not opened' }}</strong></div>
            <div><span>Agreed Fee</span><strong>{{ $file?->agreed_fee_amount ? number_format($file->agreed_fee_amount, 2) : '-' }}</strong></div>
            <div><span>Matter</span><strong>{{ $matter?->reference_no ?: 'Not opened' }}</strong></div>
        </div>

        <div class="kfms-section-heading">
            <h3>Response Notes</h3>
        </div>
        <p class="kfms-muted-text">{{ $adr->response_notes ?: 'No response notes recorded.' }}</p>
    </section>
@endsection
