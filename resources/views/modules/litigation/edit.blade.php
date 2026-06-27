@extends('layouts.admin')

@section('title', 'Edit Court Event')
@section('page-title', 'Edit Court Event')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit Court Event</h2>
                <span>Update the scheduling details for this matter.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('litigation.show', $event) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Event
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('litigation.update', $event) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('modules.litigation.partials.form', ['event' => $event])

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </section>
@endsection
