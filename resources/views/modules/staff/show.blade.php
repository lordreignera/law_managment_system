@extends('layouts.admin')

@section('title', 'Staff Profile')
@section('page-title', 'Staff Profile')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $staff->name }}</h2>
                <span>{{ $staff->staffProfile?->staff_no ?: $staff->email }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('staff.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back
                </a>
                @can('staff.edit')
                    <a class="kfms-btn" href="{{ route('staff.edit', $staff) }}">
                        <i class="mdi mdi-pencil-outline"></i>
                        Edit Profile
                    </a>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div>
                <span>Status</span>
                <strong>
                    <span class="kfms-status is-{{ $staff->staffProfile?->employment_status }}">
                        {{ ucfirst($staff->staffProfile?->employment_status) }}
                    </span>
                </strong>
            </div>
            <div>
                <span>Email</span>
                <strong>{{ $staff->email }}</strong>
            </div>
            <div>
                <span>Phone</span>
                <strong>{{ $staff->staffProfile?->phone ?: '-' }}</strong>
            </div>
            <div>
                <span>Job Title</span>
                <strong>{{ $staff->staffProfile?->job_title ?: '-' }}</strong>
            </div>
            <div>
                <span>Branch</span>
                <strong>{{ $staff->branch?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Department</span>
                <strong>{{ $staff->department?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Role</span>
                <strong>{{ $staff->roles->pluck('name')->join(', ') ?: '-' }}</strong>
            </div>
            <div>
                <span>Joined On</span>
                <strong>{{ $staff->staffProfile?->joined_on?->format('d M Y') ?: '-' }}</strong>
            </div>
        </div>
    </section>
@endsection
