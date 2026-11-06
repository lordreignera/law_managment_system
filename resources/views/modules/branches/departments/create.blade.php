@extends('layouts.admin')

@section('title', 'Add Department')
@section('page-title', 'Branches')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Department</h2>
                <span>Add a department under {{ $branch->name }}.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('branches.show', $branch) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Branch
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('departments.store', $branch) }}">
            @csrf
            @include('modules.branches.departments.partials.form')
            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('branches.show', $branch) }}">Cancel</a>
                <button class="kfms-btn" type="submit"><i class="mdi mdi-content-save-outline"></i> Save Department</button>
            </div>
        </form>
    </section>
@endsection
