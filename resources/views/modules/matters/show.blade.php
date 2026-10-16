@extends('layouts.admin')

@section('title', 'Matter Workspace')
@section('page-title', 'Matter Workspace')

@section('content')
    @php
        $files = $matter->files;
        $totalAgreedFee = $files->sum('agreed_fee_amount');
        $isLitigation = str($matter->practiceArea?->name ?? '')->contains('Litigation', true) || $matter->courtEvents->isNotEmpty();
        $openCourtEvents = $matter->courtEvents->whereIn('status', ['scheduled', 'adjourned']);
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $matter->reference_no }}</h2>
                <span>{{ $matter->title }} - {{ $matter->statusLabel() }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('matters.edit')
                    <a class="kfms-link-btn" href="{{ route('matters.edit', $matter) }}"><i class="mdi mdi-pencil"></i> Edit Matter</a>
                @endcan
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
                <span>Ultimate Client</span>
                <strong>{{ $matter->ultimateClient?->display_name ?: '-' }}</strong>
            </div>
            <div>
                <span>Practice Area</span>
                <strong>{{ $matter->practiceArea?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Business Industry</span>
                <strong>{{ $matter->businessIndustry?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Matter Category</span>
                <strong>{{ $matter->matterCategory?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Instruction Type</span>
                <strong>{{ $matter->instructionType?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Matter Status</span>
                <strong>{{ $matter->statusLabel() }}</strong>
            </div>
            <div>
                <span>Privacy</span>
                <strong>{{ str($matter->privacy_status ?: 'public')->headline() }}</strong>
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
                <span>Date of Instruction</span>
                <strong>{{ $matter->instruction_date?->format('d M Y') ?: '-' }}</strong>
            </div>
            <div>
                <span>Closed On</span>
                <strong>{{ $matter->closed_on?->format('d M Y') ?: '-' }}</strong>
            </div>
            <div>
                <span>Shelf</span>
                <strong>{{ $matter->shelf?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Opposite Counsel</span>
                <strong>{{ $matter->opposingCounsel?->name ?: ($matter->opposite_counsel ?: '-') }}</strong>
            </div>
            <div>
                <span>Next Action</span>
                <strong>{{ $matter->next_action ?: '-' }}</strong>
            </div>
            <div>
                <span>Next Action Due</span>
                <strong>{{ $matter->next_action_due?->format('d M Y') ?: '-' }}</strong>
            </div>
            <div>
                <span>Responsible Team</span>
                <strong>{{ $matter->assignments->pluck('user.name')->filter()->join(', ') ?: 'Unassigned' }}</strong>
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
        <p class="kfms-muted-text">{{ $matter->description ?: 'No internal instructions recorded.' }}</p>
        <div class="kfms-section-heading"><h3>Client-Visible Summary</h3></div>
        <p class="kfms-muted-text">{{ $matter->client_summary ?: 'No client summary recorded.' }}</p>

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
                            <td>
                                @can('clients.files.show')
                                    <a href="{{ route('clients.files.show', $matterFile) }}">{{ $matterFile->file_number }}</a>
                                @else
                                    {{ $matterFile->file_number }}
                                @endcan
                            </td>
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
                @can('matters.instructions.show')
                <a href="{{ route('matters.instructions.show', $matter) }}">
                    <i class="mdi mdi-file-document-outline"></i>
                    <strong>Instructions / Documents</strong>
                    <span>Review or add instructions, file notes, and supporting documents.</span>
                </a>
                @endcan

                @can('matters.bundles.index')
                <a href="{{ route('matters.bundles.index', $matter) }}">
                    <i class="mdi mdi-package-variant-closed"></i>
                    <strong>Pleadings / Document Bundles</strong>
                    <span>Assemble ordered pleadings, evidence, and court records with version history.</span>
                </a>
                @endcan

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

                @can('matters.billing.show')
                <a href="{{ route('matters.billing.show', $matter) }}">
                    <i class="mdi mdi-cash-register"></i>
                    <strong>Billing / Costs</strong>
                    <span>Create invoices and record costs, disbursements, taxation, or recovery follow-up.</span>
                </a>
                @endcan
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
                                    <td>
                                        @can('litigation.show')
                                            <a class="kfms-link-btn" href="{{ route('litigation.show', $event) }}">View</a>
                                        @endcan
                                    </td>
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
