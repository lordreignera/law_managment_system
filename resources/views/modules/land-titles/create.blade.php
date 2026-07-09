@extends('layouts.admin')

@section('title', 'Add Security')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Security</h2>
                <span>Register a security or land title record.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Securities
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('land-titles.store') }}" enctype="multipart/form-data">
            @include('modules.land-titles.partials.form', ['buttonText' => 'Save Security'])
        </form>
    </section>
@endsection
