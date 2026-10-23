@extends('layouts.admin')

@section('title', 'Client Details')
@section('page-title', 'Client Details')

@section('content')
    @php($nextOfKin = $client->nextOfKin)

    <section class="kfms-panel kfms-client-profile">
        <div class="kfms-panel-header kfms-client-profile-header">
            <div>
                <h2>{{ $client->display_name }}</h2>
                <span>{{ $client->client_no }} <span class="kfms-dot-separator"></span> {{ ucfirst($client->status) }}</span>
            </div>
            <div class="kfms-header-actions">
                <a class="kfms-link-btn" href="{{ route('clients.index') }}"><i class="mdi mdi-arrow-left"></i> Back to Clients</a>
                <a class="kfms-link-btn" href="{{ route('clients.details.edit', $client) }}"><i class="mdi mdi-pencil"></i> Edit Details</a>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <section class="kfms-client-profile-section" aria-labelledby="client-details-heading">
            <div class="kfms-section-heading kfms-client-subheading">
                <div>
                    <h3 id="client-details-heading">Client details</h3>
                    <span>Contact and identification information</span>
                </div>
            </div>
            <dl class="kfms-client-details">
                <div><dt>Client Type</dt><dd>{{ ucfirst($client->client_type) }}</dd></div>
                <div><dt>Prospect</dt><dd>{{ $client->is_prospect ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Organisation</dt><dd>{{ $client->organization_name ?: '-' }}</dd></div>
                <div><dt>Salutation</dt><dd>{{ $client->salutation?->name ?: '-' }}</dd></div>
                <div><dt>First Name</dt><dd>{{ $client->first_name ?: '-' }}</dd></div>
                <div><dt>Middle Name</dt><dd>{{ $client->middle_name ?: '-' }}</dd></div>
                <div><dt>Last Name</dt><dd>{{ $client->last_name ?: '-' }}</dd></div>
                <div><dt>Gender</dt><dd>{{ $client->gender ? ucfirst($client->gender) : '-' }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $client->phone ?: '-' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $client->email ?: '-' }}</dd></div>
                <div><dt>Client In Charge</dt><dd>{{ $client->clientInCharge?->name ?: '-' }}</dd></div>
                <div><dt>Position</dt><dd>{{ $client->position?->name ?: '-' }}</dd></div>
                <div><dt>Country</dt><dd>{{ $client->country?->name ?: '-' }}</dd></div>
                <div><dt>NIN / Passport / Registration No</dt><dd>{{ $client->nin_passport_no ?: '-' }}</dd></div>
                <div><dt>Date of Birth</dt><dd>{{ $client->date_of_birth?->format('d M Y') ?: '-' }}</dd></div>
                <div><dt>Occupation</dt><dd>{{ $client->occupation ?: '-' }}</dd></div>
                <div><dt>TIN</dt><dd>{{ $client->tin ?: '-' }}</dd></div>
                <div class="kfms-client-detail-wide"><dt>Address</dt><dd>{{ $client->address ?: 'No address recorded.' }}</dd></div>
            </dl>
        </section>

        <section class="kfms-client-profile-section" aria-labelledby="client-next-of-kin-heading">
            <div class="kfms-section-heading kfms-client-subheading">
                <div><h3 id="client-next-of-kin-heading">Next of Kin</h3></div>
            </div>
            @if ($nextOfKin)
                <dl class="kfms-client-details kfms-client-next-of-kin">
                    <div><dt>Name</dt><dd>{{ $nextOfKin->display_name }}</dd></div>
                    <div><dt>Relationship</dt><dd>{{ $nextOfKin->relationshipType?->name ?: '-' }}</dd></div>
                    <div><dt>Phone</dt><dd>{{ $nextOfKin->phone ?: '-' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $nextOfKin->email ?: '-' }}</dd></div>
                    <div><dt>Gender</dt><dd>{{ $nextOfKin->gender ? ucfirst($nextOfKin->gender) : '-' }}</dd></div>
                    <div><dt>NIN / Passport No</dt><dd>{{ $nextOfKin->nin_passport_no ?: '-' }}</dd></div>
                    <div><dt>Date of Birth</dt><dd>{{ $nextOfKin->date_of_birth?->format('d M Y') ?: '-' }}</dd></div>
                    <div><dt>Country</dt><dd>{{ $nextOfKin->country?->name ?: '-' }}</dd></div>
                    <div class="kfms-client-detail-wide"><dt>Address</dt><dd>{{ $nextOfKin->address ?: '-' }}</dd></div>
                </dl>
            @else
                <p class="kfms-client-empty-note">No next of kin recorded.</p>
            @endif
        </section>

        <section class="kfms-client-profile-section" aria-labelledby="client-adr-heading">
            <div class="kfms-section-heading kfms-client-subheading">
                <div>
                    <h3 id="client-adr-heading">Alternative Dispute Resolution (ADR)</h3>
                    <span>{{ $client->adrResolutions->count() }} recorded</span>
                </div>
                <button type="button" class="kfms-btn kfms-btn-compact" data-bs-toggle="modal" data-bs-target="#clientAdrModal">
                    <i class="mdi mdi-plus"></i> Add ADR
                </button>
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead><tr><th>ADR No</th><th>Title</th><th>Conflict Party</th><th>Response</th><th>File</th><th>Matter</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse ($client->adrResolutions as $adr)
                            @php($adrFile = $adr->file)
                            @php($adrMatter = $adrFile?->matter)
                            <tr>
                                <td>{{ $adr->adr_no }}</td>
                                <td>{{ $adr->title }}</td>
                                <td>{{ $adr->conflict_party_name }}</td>
                                <td>{{ str($adr->response)->headline() }}</td>
                                <td>{{ $adrFile?->file_number ?: '-' }}</td>
                                <td>{{ $adrMatter?->reference_no ?: '-' }}</td>
                                <td>
                                    <div class="kfms-table-actions">
                                        <a href="{{ route('clients.adr.show', $adr) }}">Review</a>
                                        @if (! $adrFile)
                                            <button type="button" data-bs-toggle="modal" data-bs-target="#clientFileModal" data-client-open-file data-adr-id="{{ $adr->id }}" data-adr-number="{{ $adr->adr_no }}" data-file-name="{{ $adr->title }}">Open File</button>
                                        @elseif (! $adrMatter && $client->status === 'active' && ! $client->is_prospect)
                                            <button type="button" data-bs-toggle="modal" data-bs-target="#clientMatterModal" data-client-open-matter data-file-id="{{ $adrFile->id }}">Open Matter</button>
                                        @elseif (! $adrMatter)
                                            <span class="kfms-muted-text">Approve the client before opening a matter.</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="kfms-empty">No ADR resolutions recorded for this client.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-client-profile-section" aria-labelledby="client-files-heading">
            <div class="kfms-section-heading kfms-client-subheading">
                <div>
                    <h3 id="client-files-heading">Files</h3>
                    <span>{{ $client->files->count() }} recorded</span>
                </div>
                <button type="button" class="kfms-btn kfms-btn-compact" data-bs-toggle="modal" data-bs-target="#clientFileModal" data-client-open-file>
                    <i class="mdi mdi-plus"></i> Add File
                </button>
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead><tr><th>File No</th><th>File Name</th><th>Billing Type</th><th>Agreed Fee</th><th>Matter</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse ($client->files as $file)
                            <tr>
                                <td><a href="{{ route('clients.files.show', $file) }}">{{ $file->file_number }}</a></td>
                                <td>{{ $file->file_name }}</td>
                                <td>{{ $file->billingType?->name ?: '-' }}</td>
                                <td>{{ $file->agreed_fee_amount ? number_format($file->agreed_fee_amount, 2) : '-' }}</td>
                                <td>{{ $file->matter?->reference_no ?: 'Not in a matter yet' }}</td>
                                <td>
                                    @if ($file->matter)
                                        <a class="kfms-table-action-link" href="{{ route('matters.show', $file->matter) }}">View Matter</a>
                                    @elseif ($client->status === 'active' && ! $client->is_prospect)
                                        <button type="button" class="kfms-table-action-button" data-bs-toggle="modal" data-bs-target="#clientMatterModal" data-client-open-matter data-file-id="{{ $file->id }}">Open Matter</button>
                                    @else
                                        <span class="kfms-muted-text">Client approval required</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="kfms-empty">No files opened for this client.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-client-profile-section" aria-labelledby="client-matters-heading">
            <div class="kfms-section-heading kfms-client-subheading">
                <div>
                    <h3 id="client-matters-heading">Matters</h3>
                    <span>{{ $client->matters->count() }} recorded</span>
                </div>
                @if ($client->status === 'active' && ! $client->is_prospect)
                    <button type="button" class="kfms-btn kfms-btn-compact" data-bs-toggle="modal" data-bs-target="#clientMatterModal" data-client-open-matter>
                        <i class="mdi mdi-plus"></i> Add Matter
                    </button>
                @else
                    <span class="kfms-muted-text">Approve the client before opening a matter.</span>
                @endif
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead><tr><th>Matter No</th><th>Title</th><th>Practice Area</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse ($client->matters as $matter)
                            <tr>
                                <td>{{ $matter->reference_no }}</td>
                                <td>{{ $matter->title }}</td>
                                <td>{{ $matter->practiceArea?->name ?: '-' }}</td>
                                <td>{{ $matter->statusLabel() }}</td>
                                <td><a class="kfms-table-action-link" href="{{ route('matters.show', $matter) }}">Open Matter</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="kfms-empty">No matters opened for this client.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <div class="modal fade kfms-modal kfms-client-action-modal" id="clientAdrModal" tabindex="-1" aria-labelledby="clientAdrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title" id="clientAdrModalLabel">Add ADR</h5><span>{{ $client->client_no }} · {{ $client->display_name }}</span></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('modules.clients.partials.adr-form', ['inModal' => true])</div>
            </div>
        </div>
    </div>

    <div class="modal fade kfms-modal kfms-client-action-modal" id="clientFileModal" tabindex="-1" aria-labelledby="clientFileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title" id="clientFileModalLabel">Open File</h5><span>{{ $client->client_no }} · {{ $client->display_name }}</span></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('modules.files.partials.client-file-form', ['adr' => $modalAdr, 'matters' => $fileMatters, 'inModal' => true])</div>
            </div>
        </div>
    </div>

    <div class="modal fade kfms-modal kfms-client-action-modal" id="clientMatterModal" tabindex="-1" aria-labelledby="clientMatterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title" id="clientMatterModalLabel">Open Matter</h5><span>{{ $client->client_no }} · {{ $client->display_name }}</span></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('modules.matters.partials.client-open-form', ['inModal' => true])</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var adrModal = document.getElementById('clientAdrModal');
            var fileModal = document.getElementById('clientFileModal');
            var matterModal = document.getElementById('clientMatterModal');

            function openModal(modal) {
                if (modal && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            }

            if (@json(old('_client_profile_modal') === 'adr')) openModal(adrModal);
            if (@json(old('_client_profile_modal') === 'file')) openModal(fileModal);
            if (@json(old('_client_profile_modal') === 'matter')) openModal(matterModal);

            fileModal?.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (!trigger) return;
                var form = fileModal.querySelector('form');
                var adrId = trigger?.dataset.adrId || '';
                var adrIdInput = form.querySelector('[data-client-adr-id]');
                var adrDisplay = form.querySelector('[data-client-adr-display]');
                var adrNumber = form.querySelector('[data-client-adr-number]');
                var fileName = form.querySelector('[data-client-file-name]');

                adrIdInput.value = adrId;
                adrDisplay.hidden = !adrId;
                adrNumber.value = trigger?.dataset.adrNumber || '';
                if (trigger?.dataset.fileName) {
                    fileName.value = trigger.dataset.fileName;
                    form.dataset.adrPrefilledFileName = '1';
                } else if (form.dataset.adrPrefilledFileName === '1') {
                    fileName.value = '';
                    delete form.dataset.adrPrefilledFileName;
                }
            });

            matterModal?.addEventListener('show.bs.modal', function (event) {
                if (!event.relatedTarget) return;
                var selectedFileId = event.relatedTarget?.dataset.fileId || '';
                var picker = matterModal.querySelector('[data-check-dropdown]');
                if (!picker) return;

                picker.querySelectorAll('[data-check-option]').forEach(function (checkbox) {
                    checkbox.checked = selectedFileId !== '' && checkbox.value === selectedFileId;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                });
                picker._checkUpdateSummary?.();
            });
        });
    </script>
@endpush
