@extends('layouts.admin')

@section('title', 'Matters')
@section('page-title', 'Matters')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Matter Register</h2>
                <span>{{ $matters->total() }} records</span>
            </div>
            <a class="kfms-btn" href="{{ route('matters.create') }}">
                <i class="mdi mdi-plus"></i>
                Add Matter
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif
        <form class="kfms-table-toolbar" method="GET" action="{{ route('matters.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, title, or client">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option>
                    <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Closed</option>
                    <option value="on_hold" @selected(($filters['status'] ?? '') === 'on_hold')>On Hold</option>
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('matters.index') }}">Reset</a>
            </div>
        </form>
        @include('modules.partials.table', [
            'headers' => ['Reference', 'Title', 'Client', 'Practice Area', 'Status'],
            'rows' => $matters->map(fn ($matter) => [$matter->reference_no, $matter->title, $matter->client?->name, $matter->practiceArea?->name, $matter->status]),
        ])
        {{ $matters->links() }}
    </section>
@endsection
