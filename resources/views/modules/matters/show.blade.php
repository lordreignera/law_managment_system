@extends('layouts.admin')

@section('title', 'Matter Workspace')
@section('page-title', 'Matter Workspace')

@section('content')
    @php
        $files = $matter->files;
        $totalAgreedFee = $files->sum('agreed_fee_amount');
        $isLitigation = str($matter->practiceArea?->name ?? '')->contains('Litigation', true);
        $openCourtEvents = $matter->courtEvents->whereIn('status', ['scheduled', 'adjourned']);
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $matter->reference_no }}</h2>
                <span>{{ $matter->title }} - {{ $matter->statusLabel() }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                @if ($isLitigation)
                    @can('litigation.create')
                        <a class="kfms-btn" href="{{ route('litigation.create', ['matter_id' => $matter->id]) }}">
                            <i class="mdi mdi-calendar-plus"></i>
                            Schedule Court Event
                        </a>
                    @endcan
                @endif
                @can('letters.create')
                    <a class="kfms-link-btn" href="{{ route('letters.create', ['matter_id' => $matter->id, 'client_id' => $matter->client_id]) }}">
                        <i class="mdi mdi-file-sign"></i>
                        Create Letter
                    </a>
                @endcan
                <a class="kfms-link-btn" href="{{ route('matters.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back to Matters
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div>
                <span>Client</span>
                <strong>{{ $matter->client?->display_name ?: '-' }}</strong>
            </div>
            <div>
                <span>Practice Area</span>
                <strong>{{ $matter->practiceArea?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Matter Status</span>
                <strong>{{ $matter->statusLabel() }}</strong>
            </div>
            <div>
                <span>Files</span>
                <strong>{{ $files->count() }}</strong>
            </div>
            <div>
                <span>Total Agreed Fee</span>
                <strong>{{ number_format($totalAgreedFee, 2) }}</strong>
            </div>
            <div>
                <span>Opened On</span>
                <strong>{{ $matter->opened_on?->format('d M Y') ?: '-' }}</strong>
            </div>
            <div>
                <span>Responsible Team</span>
                <strong>{{ $matter->assignments->count() }} assigned</strong>
            </div>
            <div>
                <span>Court Events</span>
                <strong>{{ $matter->courtEvents->count() }}</strong>
            </div>
            <div>
                <span>Open Court Work</span>
                <strong>{{ $openCourtEvents->count() }}</strong>
            </div>
        </div>

        <div class="kfms-section-heading">
            <h3>File Summary</h3>
        </div>
        <p class="kfms-muted-text">{{ $matter->description ?: 'No description recorded.' }}</p>

        <div class="kfms-section-heading">
            <h3>Files Under This Matter</h3>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>File No</th>
                        <th>File Name</th>
                        <th>Billing Type</th>
                        <th>Agreed Fee</th>
                        <th>Documents</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($files as $matterFile)
                        <tr>
                            <td><a href="{{ route('clients.files.show', $matterFile) }}">{{ $matterFile->file_number }}</a></td>
                            <td>{{ $matterFile->file_name }}</td>
                            <td>{{ $matterFile->billingType?->name ?: '-' }}</td>
                            <td>{{ $matterFile->agreed_fee_amount ? number_format($matterFile->agreed_fee_amount, 2) : '-' }}</td>
                            <td>{{ $matterFile->attachments->count() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="kfms-empty">No files linked to this matter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>What Happens Next</h2>
                    <span>The matter is open. Use this workspace to move the file through the correct work stream.</span>
                </div>
            </div>

            <div class="kfms-workflow-actions">
                <a href="{{ route('matters.instructions.show', $matter) }}">
                    <i class="mdi mdi-file-document-outline"></i>
                    <strong>Instructions / Documents</strong>
                    <span>Review or add instructions, file notes, and supporting documents.</span>
                </a>

                @if ($isLitigation)
                    @can('litigation.create')
                        <a href="{{ route('litigation.create', ['matter_id' => $matter->id]) }}">
                            <i class="mdi mdi-gavel"></i>
                            <strong>Add Cause List / Court File</strong>
                            <span>Capture court, case number, judge, filing, service, or next hearing.</span>
                        </a>
                    @endcan
                    @can('litigation.index')
                        <a href="{{ route('litigation.index', ['search' => $matter->reference_no]) }}">
                            <i class="mdi mdi-calendar-search"></i>
                            <strong>Open Cause List</strong>
                            <span>Track hearings, rulings, judgments, outcomes, and next steps for this file.</span>
                        </a>
                    @endcan
                @else
                    <a href="{{ route('matters.index', ['status' => 'active']) }}">
                        <i class="mdi mdi-briefcase-check-outline"></i>
                        <strong>Proceed With Matter Work</strong>
                        <span>Keep this as a non-litigation file unless it later needs court action.</span>
                    </a>
                @endif

                <a href="{{ route('matters.billing.show', $matter) }}">
                    <i class="mdi mdi-cash-register"></i>
                    <strong>Billing / Costs</strong>
                    <span>Create invoices and record costs, disbursements, taxation, or recovery follow-up.</span>
                </a>
            </div>
        </section>

        @if ($isLitigation)
            <section class="kfms-panel">
                <div class="kfms-panel-header">
                    <div>
                        <h2>Court Work</h2>
                        <span>Diary and litigation activity linked to this matter</span>
                    </div>
                </div>

                <div class="kfms-table-wrap">
                    <table class="kfms-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Court</th>
                                <th>Case No.</th>
                                <th>Advocate</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($matter->courtEvents->sortBy('starts_at') as $event)
                                <tr>
                                    <td>{{ $event->starts_at?->format('d M Y, H:i') }}</td>
                                    <td>{{ $event->eventTypeLabel() }}</td>
                                    <td>{{ $event->court?->name ?: $event->court_name ?: '-' }}</td>
                                    <td>{{ $event->case_number ?: '-' }}</td>
                                    <td>{{ $event->assignee?->name ?: '-' }}</td>
                                    <td><span class="kfms-status kfms-status-{{ $event->status }}">{{ $event->statusLabel() }}</span></td>
                                    <td><a class="kfms-link-btn" href="{{ route('litigation.show', $event) }}">View</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="kfms-empty">No court events have been scheduled for this matter yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
@endsection
