@extends('layouts.admin')

@section('title', 'Branches')
@section('page-title', 'Branches')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Branches</h2>
                <span>{{ $branches->total() }} branch{{ $branches->total() === 1 ? '' : 'es' }}</span>
            </div>
            <a class="kfms-btn" href="{{ route('branches.create') }}">
                <i class="mdi mdi-plus"></i>
                Add Branch
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('branches.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, code or city">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply</button>
                <a class="kfms-link-btn" href="{{ route('branches.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>City</th>
                        <th>Staff</th>
                        <th>Departments</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>{{ $branch->code }}</td>
                            <td><strong>{{ $branch->name }}</strong></td>
                            <td>{{ $branch->city ?: '-' }}</td>
                            <td>{{ number_format($branch->users_count) }}</td>
                            <td>{{ number_format($branch->departments_count) }}</td>
                            <td>
                                <span class="kfms-status kfms-status-{{ $branch->is_active ? 'approved' : 'rejected' }}">
                                    {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td><a class="kfms-link-btn" href="{{ route('branches.edit', $branch) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No branches yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $branches->links() }}
    </section>
@endsection
