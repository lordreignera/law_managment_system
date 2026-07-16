@extends('layouts.admin')

@section('title', 'Edit Chart Account')
@section('page-title', 'Edit Chart Account')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $account->fullName() }}</h2>
                <span>Update chart account settings and reporting flags.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('finance.chart-accounts.show', $account) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('finance.chart-accounts.update', $account) }}">
            @csrf
            @method('PUT')
            @include('modules.finance.chart-accounts.partials.form')
            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Update Account
                </button>
            </div>
        </form>
    </section>
@endsection
