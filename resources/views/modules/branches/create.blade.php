@extends('layouts.admin')

@section('title', 'Add Branch')
@section('page-title', 'Add Branch')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Branch</h2>
                <span>Create a new office branch.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('branches.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Branches
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('branches.store') }}">
            @csrf
            @include('modules.branches.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Branch
                </button>
            </div>
        </form>
    </section>
@endsection
