@extends('layouts.admin')

@section('title', 'Record Expense')
@section('page-title', 'Record Expense')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Record Expenditure</h2>
                <span>Capture a firm expense or disbursement.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('expenses.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Expenses
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>Reference Number</span>
                    <input type="text" value="{{ $referenceNumber }}" readonly disabled>
                </label>

                <label>
                    <span>Category</span>
                    <select name="expense_category_id">
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('expense_category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_category_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Related Matter (optional)</span>
                    <select name="matter_id">
                        <option value="">No matter</option>
                        @foreach ($matters as $matter)
                            <option value="{{ $matter->id }}" @selected(old('matter_id') == $matter->id)>{{ $matter->reference_no }} — {{ \Illuminate\Support\Str::limit($matter->title, 40) }}</option>
                        @endforeach
                    </select>
                    @error('matter_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Payment Source</span>
                    <select name="payment_source" required>
                        @foreach ($paymentSources as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_source', 'bank') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_source') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Description</span>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="255" required>
                    @error('description') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Payee (optional)</span>
                    <input type="text" name="payee" value="{{ old('payee') }}" maxlength="255">
                    @error('payee') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Date Spent</span>
                    <input type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}" required>
                    @error('spent_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Quantity (optional)</span>
                    <input type="number" step="0.01" min="0" name="quantity" value="{{ old('quantity') }}">
                    @error('quantity') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Unit Price (optional)</span>
                    <input type="number" step="0.01" min="0" name="unit_price" value="{{ old('unit_price') }}">
                    @error('unit_price') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Amount</span>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required>
                    @error('amount') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Notes</span>
                    <textarea name="notes" rows="3" placeholder="Optional notes">{{ old('notes') }}</textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Receipt / Document (optional)</span>
                    <input type="file" name="attachment">
                    @error('attachment') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Expense
                </button>
            </div>
        </form>
    </section>
@endsection
