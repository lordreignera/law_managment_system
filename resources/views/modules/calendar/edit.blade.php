@extends('layouts.admin')

@section('title', 'Edit Event')
@section('page-title', 'Edit Calendar Event')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit Event</h2>
                <span>Update the details of this calendar event.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('calendar.show', $event) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Event
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('calendar.update', $event) }}">
            @csrf
            @method('PUT')
            @include('modules.calendar.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </section>
@endsection
