@extends('layouts.admin')

@section('title', 'Edit Department')
@section('page-title', 'Branches')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit Department</h2>
                <span>{{ $department->name }} · {{ $branch->name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('branches.show', $branch) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Branch
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('departments.update', [$branch, $department]) }}">
            @csrf
            @method('PUT')
            @include('modules.branches.departments.partials.form')
            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('branches.show', $branch) }}">Cancel</a>
                <button class="kfms-btn" type="submit"><i class="mdi mdi-content-save-outline"></i> Save Changes</button>
            </div>
        </form>
    </section>
@endsection
