@extends('layouts.admin')

@section('title', 'Chart Account')
@section('page-title', 'Chart Account')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="kfms-alert kfms-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>{{ $account->fullName() }}</h2>
                    <span><span class="kfms-status kfms-status-{{ $account->is_active ? 'active' : 'rejected' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></span>
                </div>
                <div class="kfms-toolbar-actions">
                    @can('finance.chart-accounts.edit')
                        <a class="kfms-link-btn" href="{{ route('finance.chart-accounts.edit', $account) }}"><i class="mdi mdi-pencil"></i> Edit</a>
                    @endcan
                    <a class="kfms-link-btn" href="{{ route('finance.chart-accounts.index') }}"><i class="mdi mdi-arrow-left"></i> Back</a>
                    @can('finance.chart-accounts.destroy')
                        <form method="POST" action="{{ route('finance.chart-accounts.destroy', $account) }}" onsubmit="return confirm('Delete this chart account?');">
                            @csrf
                            @method('DELETE')
                            <button class="kfms-link-btn kfms-link-btn-danger" type="submit"><i class="mdi mdi-delete"></i> Delete</button>
                        </form>
                    @endcan
                </div>
            </div>

            <dl class="kfms-detail-list kfms-detail-list-bordered">
                <div><dt>Account Number</dt><dd>{{ $account->account_number }}</dd></div>
                <div><dt>Account Class</dt><dd>{{ $account->accountClass?->name ?: '-' }}</dd></div>
                <div><dt>Parent Account</dt><dd>{{ $account->parent?->fullName() ?: '-' }}</dd></div>
                <div><dt>Type</dt><dd>{{ $account->typeLabel() }}</dd></div>
                <div><dt>Normal Balance</dt><dd>{{ $account->normalBalanceLabel() }}</dd></div>
                <div><dt>Currency</dt><dd>{{ $account->currency_code ?: '-' }}</dd></div>
                <div><dt>Postable</dt><dd>{{ $account->is_postable ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Bank Account</dt><dd>{{ $account->is_bank_account ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Cash Account</dt><dd>{{ $account->is_cash_account ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Client Funds</dt><dd>{{ $account->is_client_funds_account ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Source Row</dt><dd>{{ $account->source_row ?: '-' }}</dd></div>
                <div><dt>Description</dt><dd>{{ $account->description ?: '-' }}</dd></div>
            </dl>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Child Accounts</h2>
                    <span>Accounts grouped under this control account</span>
                </div>
                @can('finance.chart-accounts.create')
                    <a class="kfms-link-btn" href="{{ route('finance.chart-accounts.create', ['account_class_id' => $account->account_class_id, 'parent_id' => $account->id]) }}">
                        <i class="mdi mdi-plus"></i>
                        Add Child
                    </a>
                @endcan
            </div>

            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr><th>No.</th><th>Name</th><th>Type</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($account->children as $child)
                            <tr>
                                <td>{{ $child->account_number }}</td>
                                <td><a href="{{ route('finance.chart-accounts.show', $child) }}">{{ $child->name }}</a></td>
                                <td>{{ $child->typeLabel() }}</td>
                                <td>{{ $child->is_active ? 'Active' : 'Inactive' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="kfms-empty">No child accounts.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($account->mappings->isNotEmpty())
                <div class="kfms-panel-subheader"><h3>System Mappings</h3></div>
                <div class="kfms-action-list">
                    @foreach ($account->mappings as $mapping)
                        <span>
                            <strong>{{ str($mapping->module)->headline() }} / {{ str($mapping->mapping_key)->headline() }}</strong>
                            <em>{{ $mapping->notes }}</em>
                        </span>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
