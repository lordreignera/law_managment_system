@extends('layouts.admin')

@section('title', 'Edit Recovery')
@section('page-title', 'Edit Recovery Account')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit Recovery Account</h2>
                <span>{{ $account->debtor_name }} — {{ $account->client?->name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('recoveries.show', $account) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('recoveries.update', $account) }}">
            @csrf
            @method('PUT')
            @include('modules.recoveries.partials.form')

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Update Recovery
                </button>
            </div>
        </form>
    </section>
@endsection
