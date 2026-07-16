@extends('layouts.admin')

@section('title', 'Letters & Opinions')
@section('page-title', 'Letters & Opinions')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Letters Register</h2>
                <span>{{ $letters->total() }} letters, opinions, notices, and client documents</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('letters.dashboard')
                    <a class="kfms-link-btn" href="{{ route('letters.dashboard') }}">
                        <i class="mdi mdi-view-dashboard-outline"></i>
                        Dashboard
                    </a>
                @endcan
                @can('letters.templates.index')
                    <a class="kfms-link-btn" href="{{ route('letters.templates.index') }}">
                        <i class="mdi mdi-file-cog-outline"></i>
                        Templates
                    </a>
                @endcan
                @can('letters.create')
                    <a class="kfms-btn" href="{{ route('letters.create') }}">
                        <i class="mdi mdi-plus"></i>
                        Create Letter
                    </a>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">{{ $errors->first() }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('letters.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, subject, recipient, client or matter">
            </label>
            <label>
                <span>Type</span>
                <select name="letter_type">
                    <option value="">All Types</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['letter_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('letters.index') }}">Reset</a>
            </div>
        </form>

        @include('modules.letters.partials.table', ['letters' => $letters])

        {{ $letters->links() }}
    </section>
@endsection
