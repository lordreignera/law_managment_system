@extends('layouts.admin')

@section('title', 'Approval Requests')
@section('page-title', 'Access Control')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Approval Requests</h2>
                <span>Select complete requests to approve together, or open Review to correct a request.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('access.users.index') }}">
                <i class="mdi mdi-account-group-outline"></i>
                All Users
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="kfms-alert" role="alert">{{ $errors->first() }}</div>
        @endif
        @if (session('approval_notice_failures'))
            <div class="kfms-alert" role="status">Accounts were approved, but {{ session('approval_notice_failures') }} approval notice(s) could not be delivered. Check notification settings.</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('access.approvals.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search pending request">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('access.approvals.index') }}">Reset</a>
            </div>
        </form>

        <div data-bulk-approvals>
        @can('access.users.approve')
            <form id="bulk-approvals" class="kfms-bulk-toolbar" method="POST" action="{{ route('access.users.bulk-approve') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                <div>
                    <strong data-bulk-count role="status">0 selected</strong>
                    <p>Each user keeps the requested role, branch, and department shown below. Incomplete requests need review.</p>
                </div>
                <div class="kfms-toolbar-actions">
                    <button class="kfms-link-btn" type="button" data-bulk-first="5">Select 5</button>
                    <button class="kfms-link-btn" type="button" data-bulk-first="10">Select 10</button>
                    <button class="kfms-btn" type="submit" data-bulk-submit>Approve Selected</button>
                </div>
            </form>
        @endcan
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        @can('access.users.approve')
                            <th class="kfms-selection-cell"><input type="checkbox" data-bulk-all aria-label="Select all complete requests on this page"></th>
                        @endcan
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Job Title</th>
                        <th>Branch</th>
                        <th>Department</th>
                        <th>Requested Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($profiles as $profile)
                        @php
                            $canSelect = $profile->user && $profile->user->account_type !== 'client'
                                && filled($profile->phone) && filled($profile->job_title)
                                && $profile->branch?->is_active && $profile->department?->is_active
                                && (! $profile->department->branch_id || (int) $profile->department->branch_id === (int) $profile->branch_id)
                                && $roles->contains('name', $profile->requested_role)
                                && ! $profile->user->hasAnyRole(['Super Admin', 'Administrator']);
                        @endphp
                        <tr>
                            @can('access.users.approve')
                                <td class="kfms-selection-cell"><input type="checkbox" name="user_ids[]" value="{{ $profile->user_id }}" form="bulk-approvals" data-bulk-user aria-label="Select {{ $profile->user?->name }}" @disabled(! $canSelect) @checked($canSelect && in_array((string) $profile->user_id, array_map('strval', old('user_ids', [])), true))></td>
                            @endcan
                            <td>{{ $profile->user?->name }}</td>
                            <td>{{ $profile->user?->email }}</td>
                            <td>{{ $profile->phone ?: '-' }}</td>
                            <td>{{ $profile->job_title ?: '-' }}</td>
                            <td>{{ $profile->branch?->name ?: '-' }}</td>
                            <td>{{ $profile->department?->name ?: '-' }}</td>
                            <td>{{ $profile->requested_role ?: '-' }}</td>
                            <td>
                                <div class="kfms-table-actions">
                                    <a href="{{ route('access.approvals.show', $profile) }}">Review</a>
                                    @if (! $canSelect)<span class="kfms-muted-text">Review first</span>@endif
                                    <form method="POST" action="{{ route('access.users.destroy', $profile->user) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="redirect_to" value="approvals">
                                        <button class="is-danger" type="submit" onclick="return confirm('Delete this access request?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('access.users.approve') ? 9 : 8 }}" class="kfms-empty">No pending access requests.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        </div>
        {{ $profiles->links() }}
    </section>
@endsection
