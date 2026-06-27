@extends('layouts.admin')

@section('title', 'Edit Holiday')
@section('page-title', 'Edit Public Holiday')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit Public Holiday</h2>
                <span>Update this holiday.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('holidays.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Holidays
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('holidays.update', $holiday) }}">
            @csrf
            @method('PUT')
            @include('modules.holidays.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </section>
@endsection
