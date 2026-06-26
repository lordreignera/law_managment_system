@extends('layouts.admin')

@section('title', 'Clients')
@section('page-title', 'Clients')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Client Register</h2>
                <span>{{ $clients->total() }} records</span>
            </div>
            <a class="kfms-btn" href="{{ route('clients.create') }}">
                <i class="mdi mdi-plus"></i>
                Add Client
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('clients.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, phone, or email">
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

        @include('modules.partials.table', [
            'headers' => ['Name', 'Type', 'Phone', 'Email', 'Status'],
            'rows' => $clients->map(fn ($client) => [$client->display_name, $client->client_type, $client->phone, $client->email, $client->status]),
        ])
        {{ $clients->links() }}
    </section>
@endsection
