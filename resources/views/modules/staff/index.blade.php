@extends('layouts.admin')

@section('title', 'Staff')
@section('page-title', 'Staff')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <h2>Staff Directory</h2>
            <span>{{ $staff->total() }} records</span>
        </div>
        @include('modules.partials.table', [
            'headers' => ['Name', 'Email', 'Branch', 'Department', 'Roles'],
            'rows' => $staff->map(fn ($user) => [$user->name, $user->email, $user->branch?->name, $user->department?->name, $user->roles->pluck('name')->join(', ')]),
        ])
        {{ $staff->links() }}
    </section>
@endsection
