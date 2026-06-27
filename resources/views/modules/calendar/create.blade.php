@extends('layouts.admin')

@section('title', 'Schedule Event')
@section('page-title', 'Schedule Calendar Event')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Schedule Event</h2>
                <span>Add a meeting, appointment or reminder to the firm calendar.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('calendar.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Calendar
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('calendar.store') }}">
            @csrf
            @include('modules.calendar.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-calendar-plus"></i>
                    Schedule Event
                </button>
            </div>
        </form>
    </section>
@endsection
