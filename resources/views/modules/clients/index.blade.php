@extends('layouts.admin')

@section('title', 'Approved Clients')
@section('page-title', 'Approved Clients')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Approved Client Register</h2>
                <span>{{ $clients->total() }} approved client records</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('clients.export')
                    <a class="kfms-link-btn" href="{{ route('clients.export') }}">
                        <i class="mdi mdi-microsoft-excel"></i>
                        Export
                    </a>
                @endcan
                @can('clients.import')
                    <form class="kfms-inline-upload" method="POST" action="{{ route('clients.import') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                        <button type="submit"><i class="mdi mdi-upload"></i> Import</button>
                    </form>
                    <a class="kfms-link-btn" href="{{ route('imports.templates.download', 'clients') }}">Template</a>
                @endcan
                @can('intakes.create')
                    <a class="kfms-btn" href="{{ route('intakes.create') }}">
                        <i class="mdi mdi-plus"></i>
                        New Client Intake
                    </a>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('clients.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search number, name, phone, or email">
            </label>
            <label>
                <span>Type</span>
                <select name="client_type">
                    <option value="">All Types</option>
                    <option value="individual" @selected(($filters['client_type'] ?? '') === 'individual')>Individual</option>
                    <option value="organization" @selected(($filters['client_type'] ?? '') === 'organization')>Organization</option>
                </select>
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('clients.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Client No</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td>{{ $client->client_no }}</td>
                            <td>{{ $client->display_name }}</td>
                            <td>{{ ucfirst($client->client_type) }}</td>
                            <td>{{ $client->phone ?: '-' }}</td>
                            <td>{{ $client->email ?: '-' }}</td>
                            <td>{{ ucfirst($client->status) }}</td>
                            <td>
                                <details class="kfms-action-menu kfms-client-action-menu">
                                    <summary aria-haspopup="true" aria-expanded="false" aria-controls="client-actions-{{ $client->id }}">
                                        Options
                                    </summary>
                                    <div class="kfms-action-menu-list" id="client-actions-{{ $client->id }}" aria-label="Actions for {{ $client->display_name }}">
                                        <a class="dropdown-item" href="{{ route('clients.show', $client) }}" data-client-preview-trigger data-preview-url="{{ route('clients.show', ['client' => $client, 'modal' => 1]) }}">
                                            <i class="mdi mdi-eye"></i>
                                            View Client Details
                                        </a>
                                        <a class="dropdown-item" href="{{ route('clients.details.edit', $client) }}">
                                            <i class="mdi mdi-pencil"></i>
                                            Add More Details
                                        </a>
                                        @can('clients.adr.create')
                                            <a class="dropdown-item" href="{{ route('clients.adr.create', $client) }}">
                                                <i class="mdi mdi-handshake-outline"></i>
                                                Start ADR
                                            </a>
                                        @endcan
                                        @can('clients.files.create')
                                            <a class="dropdown-item" href="{{ route('clients.files.create', $client) }}">
                                                <i class="mdi mdi-folder-plus"></i>
                                                Open File
                                            </a>
                                        @endcan
                                        @if (auth()->user()->hasRole('Super Admin'))
                                            <button class="dropdown-item is-danger" type="button" data-client-delete-open data-delete-url="{{ route('clients.destroy', $client) }}" data-client-number="{{ $client->client_no }}" data-client-display-name="{{ $client->display_name }}">
                                                <i class="mdi mdi-delete-outline"></i>
                                                Delete Client
                                            </button>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No clients found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $clients->links() }}
    </section>

    <div class="modal fade kfms-modal kfms-client-preview-modal" id="client-preview-modal" tabindex="-1" aria-labelledby="client-preview-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title" id="client-preview-title">Client details</h2>
                        <span>Key contact details and linked work</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="client-preview-body" aria-live="polite">
                    <div class="kfms-client-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Loading client details…</div>
                </div>
                <div class="modal-footer"><button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    @if (auth()->user()->hasRole('Super Admin'))
        <div class="modal fade kfms-modal kfms-client-delete-modal" id="client-delete-modal" tabindex="-1" aria-labelledby="client-delete-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="client-delete-form" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title" id="client-delete-title">Remove client?</h2>
                                <span id="client-delete-subtitle"></span>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>This removes the client from the active register and disables their client portal access. Matters, files, and audit history remain available for the firm’s records.</p>
                        </div>
                        <div class="modal-footer">
                            <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="kfms-link-btn kfms-danger" type="submit"><i class="mdi mdi-delete-outline"></i> Remove client</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientActionMenus = Array.from(document.querySelectorAll('.kfms-client-action-menu'));
            const portalledActionMenus = new Map();

            const restoreActionMenu = (details) => {
                const state = portalledActionMenus.get(details);
                if (!state) return;

                state.menu.classList.remove('kfms-client-action-menu-portal');
                state.menu.style.removeProperty('top');
                state.menu.style.removeProperty('left');
                if (state.placeholder.parentNode) {
                    state.placeholder.parentNode.insertBefore(state.menu, state.placeholder);
                    state.placeholder.remove();
                }
                portalledActionMenus.delete(details);
                details.querySelector('summary')?.setAttribute('aria-expanded', 'false');
            };

            const positionActionMenu = (details) => {
                const state = portalledActionMenus.get(details);
                const summary = details.querySelector('summary');
                if (!state || !summary || !details.open) return;

                const anchor = summary.getBoundingClientRect();
                if (anchor.bottom < 0 || anchor.top > window.innerHeight) {
                    details.open = false;
                    restoreActionMenu(details);
                    return;
                }

                const menu = state.menu;
                const margin = 8;
                const gap = 6;
                const menuWidth = menu.getBoundingClientRect().width;
                const menuHeight = menu.getBoundingClientRect().height;
                const spaceBelow = window.innerHeight - anchor.bottom - gap;
                const spaceAbove = anchor.top - gap;
                let top = spaceBelow >= menuHeight || spaceBelow >= spaceAbove
                    ? anchor.bottom + gap
                    : anchor.top - menuHeight - gap;
                top = Math.max(margin, Math.min(top, window.innerHeight - menuHeight - margin));

                let left = anchor.right - menuWidth;
                left = Math.max(margin, Math.min(left, window.innerWidth - menuWidth - margin));
                menu.style.top = `${top}px`;
                menu.style.left = `${left}px`;
            };

            clientActionMenus.forEach((details) => {
                const actionMenu = details.querySelector('.kfms-action-menu-list');
                actionMenu?.addEventListener('click', (event) => {
                    if (!event.target.closest('a, button')) return;
                    details.open = false;
                    restoreActionMenu(details);
                });

                details.addEventListener('toggle', () => {
                    const summary = details.querySelector('summary');
                    summary?.setAttribute('aria-expanded', details.open ? 'true' : 'false');

                    if (!details.open) {
                        restoreActionMenu(details);
                        return;
                    }

                    clientActionMenus.forEach((otherMenu) => {
                        if (otherMenu !== details && otherMenu.open) otherMenu.open = false;
                    });

                    const menu = details.querySelector('.kfms-action-menu-list');
                    if (!menu) return;

                    const placeholder = document.createComment('client-action-menu-position');
                    menu.parentNode.insertBefore(placeholder, menu);
                    document.body.appendChild(menu);
                    menu.classList.add('kfms-client-action-menu-portal');
                    portalledActionMenus.set(details, { menu, placeholder });
                    positionActionMenu(details);
                    if (summary?.matches(':focus-visible')) {
                        menu.querySelector('a, button')?.focus();
                    }
                });
            });

            document.addEventListener('pointerdown', (event) => {
                portalledActionMenus.forEach((state, details) => {
                    if (!details.contains(event.target) && !state.menu.contains(event.target)) {
                        details.open = false;
                        restoreActionMenu(details);
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                portalledActionMenus.forEach((state, details) => {
                    details.open = false;
                    restoreActionMenu(details);
                    details.querySelector('summary')?.focus();
                });
            });

            const repositionOpenMenus = () => {
                portalledActionMenus.forEach((state, details) => positionActionMenu(details));
            };
            document.addEventListener('scroll', repositionOpenMenus, true);
            window.addEventListener('resize', repositionOpenMenus);

            const previewElement = document.getElementById('client-preview-modal');
            const previewBody = document.getElementById('client-preview-body');
            const previewTitle = document.getElementById('client-preview-title');
            const previewModal = window.bootstrap?.Modal && window.bootstrap.Modal.getOrCreateInstance(previewElement);

            if (!previewModal) return;

            document.querySelectorAll('[data-client-preview-trigger]').forEach((trigger) => {
                trigger.addEventListener('click', async function (event) {
                    event.preventDefault();
                    previewTitle.textContent = 'Loading client details';
                    previewBody.innerHTML = '<div class="kfms-client-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Loading client details…</div>';
                    previewModal.show();

                    try {
                        const response = await fetch(trigger.dataset.previewUrl, {
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
                        });
                        if (!response.ok) throw new Error('Could not load this client. Please try again.');
                        previewBody.innerHTML = await response.text();
                        previewTitle.textContent = 'Client details';
                    } catch (error) {
                        previewBody.innerHTML = '<div class="kfms-alert" role="alert">' + error.message + '</div>';
                        previewTitle.textContent = 'Client details';
                    }
                });
            });

            const deleteElement = document.getElementById('client-delete-modal');
            const deleteModal = deleteElement && window.bootstrap?.Modal.getOrCreateInstance(deleteElement);
            const deleteForm = document.getElementById('client-delete-form');
            const deleteSubtitle = document.getElementById('client-delete-subtitle');

            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-client-delete-open]');
                if (!trigger || !deleteModal) return;
                event.preventDefault();
                deleteForm.action = trigger.dataset.deleteUrl;
                deleteSubtitle.textContent = trigger.dataset.client-display-name + ' (' + trigger.dataset.clientNumber + ')';
                deleteModal.show();
            });
        });
    </script>
@endpush
