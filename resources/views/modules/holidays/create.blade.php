@extends('layouts.admin')

@section('title', 'Add Holiday')
@section('page-title', 'Add Public Holiday')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Public Holiday</h2>
                <span>Holidays appear on the firm calendar for everyone.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('holidays.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Holidays
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('holidays.store') }}">
            @csrf
            @include('modules.holidays.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Holiday
                </button>
            </div>
        </form>
    </section>
@endsection
