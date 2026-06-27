@extends('layouts.admin')

@section('title', 'New Petty Cash Transaction')
@section('page-title', 'New Petty Cash Transaction')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Petty Cash Transaction</h2>
                <span>Current balance: {{ number_format($balance, 2) }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('petty-cash.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Petty Cash
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('petty-cash.store') }}">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>Reference Number</span>
                    <input type="text" value="{{ $referenceNumber }}" readonly disabled>
                </label>

                <label>
                    <span>Type</span>
                    <select name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Category (for disbursements)</span>
                    <select name="expense_category_id">
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('expense_category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_category_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Amount</span>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
                    @error('amount') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Date</span>
                    <input type="date" name="transacted_on" value="{{ old('transacted_on', now()->toDateString()) }}" required>
                    @error('transacted_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Payee (optional)</span>
                    <input type="text" name="payee" value="{{ old('payee') }}" maxlength="255">
                    @error('payee') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Description</span>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="255" required>
                    @error('description') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Notes</span>
                    <textarea name="notes" rows="3" placeholder="Optional notes">{{ old('notes') }}</textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Transaction
                </button>
            </div>
        </form>
    </section>
@endsection
