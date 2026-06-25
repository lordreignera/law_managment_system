@extends('layouts.admin')

@section('title', $definition['title'])
@section('page-title', $definition['title'])

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $definition['title'] }}</h2>
                <span>{{ $definition['description'] }}</span>
            </div>
            <a class="kfms-btn" href="{{ route('settings.system.create', $setting) }}">Add {{ $definition['singular'] }}</a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        @if (in_array('court_level', $definition['extra_fields'] ?? [], true))
                            <th>Level</th>
                            <th>Station</th>
                        @endif
                        @if (in_array('symbol', $definition['extra_fields'] ?? [], true))
                            <th>Symbol</th>
                        @endif
                        @if (in_array('hourly_rate', $definition['extra_fields'] ?? [], true))
                            <th>Rate</th>
                            <th>Currency</th>
                        @endif
                        @if (in_array('is_default', $definition['extra_fields'] ?? [], true))
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
                            @if (in_array('court_level', $definition['extra_fields'] ?? [], true))
                                <td>{{ $record->court_level ?: '-' }}</td>
                                <td>{{ $record->station ?: '-' }}</td>
                            @endif
                            @if (in_array('symbol', $definition['extra_fields'] ?? [], true))
                                <td>{{ $record->symbol ?: '-' }}</td>
                            @endif
                            @if (in_array('hourly_rate', $definition['extra_fields'] ?? [], true))
                                <td>{{ number_format($record->hourly_rate, 2) }}</td>
                                <td>{{ $record->currencyType?->name ?: '-' }}</td>
                            @endif
                            @if (in_array('is_default', $definition['extra_fields'] ?? [], true))
                                <td>{{ $record->is_default ? 'Yes' : 'No' }}</td>
                            @endif
                            <td>{{ $record->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <div class="kfms-table-actions">
                                    <a href="{{ route('settings.system.edit', [$setting, $record]) }}">Edit</a>
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
                            <td colspan="8" class="kfms-empty">No records yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $records->links() }}
    </section>
@endsection
