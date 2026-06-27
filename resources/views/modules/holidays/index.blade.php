@extends('layouts.admin')

@section('title', 'Public Holidays')
@section('page-title', 'Public Holidays')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Public Holidays</h2>
                <span>{{ $holidays->total() }} holiday{{ $holidays->total() === 1 ? '' : 's' }}</span>
            </div>
            <a class="kfms-btn" href="{{ route('holidays.create') }}">
                <i class="mdi mdi-plus"></i>
                Add Holiday
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('holidays.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search holiday name">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply</button>
                <a class="kfms-link-btn" href="{{ route('holidays.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Recurs Yearly</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($holidays as $holiday)
                        <tr>
                            <td>{{ $holiday->is_recurring ? $holiday->date->format('d M') : $holiday->date->format('d M Y') }}</td>
                            <td><strong>{{ $holiday->name }}</strong></td>
                            <td>{{ $holiday->is_recurring ? 'Yes' : 'No' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($holiday->notes, 40) ?: '-' }}</td>
                            <td class="kfms-row-actions">
                                <a class="kfms-link-btn" href="{{ route('holidays.edit', $holiday) }}">Edit</a>
                                <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" onsubmit="return confirm('Remove this holiday?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="kfms-link-btn kfms-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="kfms-empty">No public holidays configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $holidays->links() }}
    </section>
@endsection
