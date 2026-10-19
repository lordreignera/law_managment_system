@extends('layouts.admin')

@section('title', 'Add Security')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Security</h2>
                <span>Register and track a security instrument.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('securities.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Securities
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('securities.store') }}" enctype="multipart/form-data">
            @include('modules.securities.partials.form', ['buttonText' => 'Save Security'])
        </form>
    </section>
@endsection
