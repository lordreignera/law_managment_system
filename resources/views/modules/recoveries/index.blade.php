@extends('layouts.admin')

@section('title', 'Recoveries')
@section('page-title', 'Recoveries')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <h2>Recovery Accounts</h2>
            <span>{{ $accounts->total() }} records</span>
        </div>
        @include('modules.partials.table', [
            'headers' => ['Debtor', 'Bank/Client', 'Account', 'Outstanding', 'Recovered', 'Status'],
            'rows' => $accounts->map(fn ($account) => [$account->debtor_name, $account->client?->name, $account->account_number, number_format($account->outstanding_amount), number_format($account->amount_recovered), $account->status]),
        ])
        {{ $accounts->links() }}
    </section>
@endsection
