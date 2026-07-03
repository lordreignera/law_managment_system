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
                                <details class="kfms-action-menu">
                                    <summary>
                                        Options
                                    </summary>
                                    <div class="kfms-action-menu-list">
                                        <a class="dropdown-item" href="{{ route('clients.show', $client) }}">
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
@endsection
