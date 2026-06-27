@extends('layouts.admin')

@section('title', 'Add Recovery')
@section('page-title', 'Add Recovery Account')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Recovery Account</h2>
                <span>Register a debt to recover and assign it to a recovery officer.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('recoveries.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Recoveries
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('recoveries.store') }}">
            @csrf
            @include('modules.recoveries.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Recovery
                </button>
            </div>
        </form>
    </section>
@endsection
