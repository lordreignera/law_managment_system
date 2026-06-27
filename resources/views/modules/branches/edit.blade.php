@extends('layouts.admin')

@section('title', 'Edit Branch')
@section('page-title', 'Edit Branch')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit Branch</h2>
                <span>Update branch details.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('branches.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Branches
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('branches.update', $branch) }}">
            @csrf
            @method('PUT')
            @include('modules.branches.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </section>
@endsection
