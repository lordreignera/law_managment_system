@extends('layouts.admin')

@section('title', 'Add Cause List / Court File')
@section('page-title', 'Add Cause List / Court File')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Cause List / Court File</h2>
                <span>Capture court, case number, judge, filing, service, hearing, or deadline for this matter.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('litigation.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Cause List
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('litigation.store') }}" enctype="multipart/form-data">
            @csrf
            @include('modules.litigation.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-calendar-plus"></i>
                    Save Cause List Entry
                </button>
            </div>
        </form>
    </section>
@endsection
