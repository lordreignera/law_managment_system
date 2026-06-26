@extends('layouts.admin')

@section('title', $definition['title'])
@section('page-title', $definition['title'])

@section('content')
    @php
        $extraFields = $definition['extra_fields'] ?? [];
        $tableColumns = 5
            + (in_array('court_level', $extraFields, true) ? 2 : 0)
            + (in_array('symbol', $extraFields, true) ? 1 : 0)
            + (in_array('hourly_rate', $extraFields, true) ? 2 : 0)
            + (in_array('is_default', $extraFields, true) ? 1 : 0);
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $definition['title'] }}</h2>
                <span>{{ $definition['description'] }}</span>
            </div>
            <div class="kfms-header-actions">
                <a class="kfms-link-btn" href="{{ route('settings.system.overview') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    System Settings
                </a>
                <button class="kfms-btn" type="button" data-bs-toggle="modal" data-bs-target="#create-setting-modal">
                    <i class="mdi mdi-plus"></i>
                    Add {{ $definition['singular'] }}
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('settings.system.index', $setting) }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code, name, or description">
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
                <a class="kfms-link-btn" href="{{ route('settings.system.index', $setting) }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        @if (in_array('court_level', $extraFields, true))
                            <th>Level</th>
                            <th>Station</th>
                        @endif
                        @if (in_array('symbol', $extraFields, true))
                            <th>Symbol</th>
                        @endif
                        @if (in_array('hourly_rate', $extraFields, true))
                            <th>Rate</th>
                            <th>Currency</th>
                        @endif
                        @if (in_array('is_default', $extraFields, true))
                            <th>Default</th>
                        @endif
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->code }}</td>
                            <td>{{ $record->name }}</td>
                            @if (in_array('court_level', $extraFields, true))
                                <td>{{ $record->court_level ?: '-' }}</td>
                                <td>{{ $record->station ?: '-' }}</td>
                            @endif
                            @if (in_array('symbol', $extraFields, true))
                                <td>{{ $record->symbol ?: '-' }}</td>
                            @endif
                            @if (in_array('hourly_rate', $extraFields, true))
                                <td>{{ number_format($record->hourly_rate, 2) }}</td>
                                <td>{{ $record->currencyType?->name ?: '-' }}</td>
                            @endif
                            @if (in_array('is_default', $extraFields, true))
                                <td>{{ $record->is_default ? 'Yes' : 'No' }}</td>
                            @endif
                            <td>
                                <span class="kfms-status {{ $record->is_active ? 'is-active' : 'is-muted' }}">
                                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="kfms-table-actions">
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#edit-setting-modal-{{ $record->id }}">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('settings.system.destroy', [$setting, $record]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Delete this record?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $tableColumns }}" class="kfms-empty">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $records->links() }}
    </section>

    <div class="modal fade kfms-modal" id="create-setting-modal" tabindex="-1" aria-labelledby="create-setting-modal-label" aria-hidden="true">
        <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="create-setting-modal-label">Add {{ $definition['singular'] }}</h5>
                        <span>{{ $definition['description'] }}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="kfms-form" method="POST" action="{{ route('settings.system.store', $setting) }}">
                    @csrf
                    <div class="modal-body">
                        @include('modules.system-settings.partials.form', ['record' => $newRecord, 'isModal' => true])
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($records as $record)
        <div class="modal fade kfms-modal" id="edit-setting-modal-{{ $record->id }}" tabindex="-1" aria-labelledby="edit-setting-modal-label-{{ $record->id }}" aria-hidden="true">
            <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="edit-setting-modal-label-{{ $record->id }}">Edit {{ $definition['singular'] }}</h5>
                            <span>{{ $record->code }}</span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="kfms-form" method="POST" action="{{ route('settings.system.update', [$setting, $record]) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-body">
                            @include('modules.system-settings.partials.form', ['record' => $record, 'isModal' => true])
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
