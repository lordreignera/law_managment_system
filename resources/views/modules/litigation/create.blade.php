@extends('layouts.admin')

@section('title', 'Schedule Court Event')
@section('page-title', 'Schedule Court Event')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Schedule Court Event</h2>
                <span>Add a mention, hearing or deadline to the cause list.</span>
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
                    Schedule Event
                </button>
            </div>
        </form>
    </section>
@endsection
