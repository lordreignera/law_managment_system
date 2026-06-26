@extends('layouts.admin')

@section('title', 'Staff')
@section('page-title', 'Staff')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <h2>Staff Directory</h2>
            <span>{{ $staff->total() }} records</span>
        </div>
        <form class="kfms-table-toolbar" method="GET" action="{{ route('staff.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email, branch, department, or role">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('staff.index') }}">Reset</a>
            </div>
        </form>
        @include('modules.partials.table', [
            'headers' => ['Name', 'Email', 'Branch', 'Department', 'Roles'],
            'rows' => $staff->map(fn ($user) => [$user->name, $user->email, $user->branch?->name, $user->department?->name, $user->roles->pluck('name')->join(', ')]),
        ])
        {{ $staff->links() }}
    </section>
@endsection
