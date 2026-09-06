@extends('layouts.admin')

@section('title', 'Add Chart Account')
@section('page-title', 'Add Chart Account')

@section('content')
    @php($fromDashboard = request('from') === 'finance-dashboard')

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Chart Account</h2>
                <span>Create a finance account with an auto-generated number.</span>
            </div>
            <a class="kfms-link-btn" href="{{ $fromDashboard ? route('finance.dashboard') : route('finance.chart-accounts.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                {{ $fromDashboard ? 'Back to Finance Dashboard' : 'Back' }}
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('finance.chart-accounts.store') }}">
            @csrf
            @if ($fromDashboard)
                <input type="hidden" name="from" value="finance-dashboard">
            @endif
            @include('modules.finance.chart-accounts.partials.form')
            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Account
                </button>
            </div>
        </form>
    </section>
@endsection
