@extends('layouts.admin')

@section('title', 'Add Invoice')
@section('page-title', 'Add Invoice')

@section('content')
    @php($fromDashboard = request('from') === 'finance-dashboard')

    <section class="kfms-panel kfms-finance-form-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Add Invoice</h2>
                <span>Create a client invoice for finance tracking and payment follow-up.</span>
            </div>
            <a class="kfms-link-btn" href="{{ $fromDashboard ? route('finance.dashboard') : route('finance.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                {{ $fromDashboard ? 'Back to Finance Dashboard' : 'Back to Finance Overview' }}
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('finance.invoices.store') }}">
            @csrf
            @if ($fromDashboard)
                <input type="hidden" name="from" value="finance-dashboard">
            @endif

            <div class="kfms-form-grid">
                <label>
                    <span>Invoice Number</span>
                    <input type="text" value="{{ $invoiceNumber }}" readonly disabled>
                </label>

                <label>
                    <span>Client</span>
                    <select name="client_id" id="finance-invoice-client" required>
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->display_name }}</option>
                        @endforeach
                    </select>
                    @error('client_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Related Matter (optional)</span>
                    <select name="matter_id" id="finance-invoice-matter">
                        <option value="">No matter</option>
                        @foreach ($matters as $matter)
                            <option value="{{ $matter->id }}" data-client="{{ $matter->client_id }}" @selected(old('matter_id') == $matter->id)>
                                {{ $matter->reference_no }} - {{ \Illuminate\Support\Str::limit($matter->title, 52) }}
                            </option>
                        @endforeach
                    </select>
                    @error('matter_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Status</span>
                    <select name="status" required>
                        @foreach ($invoiceStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'sent') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Invoice Date</span>
                    <input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required>
                    @error('invoice_date') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Due Date</span>
                    <input type="date" name="due_date" value="{{ old('due_date') }}">
                    @error('due_date') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Subtotal</span>
                    <input type="number" step="0.01" min="0" name="subtotal" value="{{ old('subtotal') }}" required>
                    @error('subtotal') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Tax</span>
                    <input type="number" step="0.01" min="0" name="tax" value="{{ old('tax', 0) }}">
                    @error('tax') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Invoice
                </button>
            </div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const client = document.getElementById('finance-invoice-client');
            const matter = document.getElementById('finance-invoice-matter');

            if (!client || !matter) {
                return;
            }

            const filterMatters = () => {
                Array.from(matter.options).forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    option.hidden = client.value && option.dataset.client !== client.value;
                });

                if (matter.selectedOptions[0]?.hidden) {
                    matter.value = '';
                }
            };

            client.addEventListener('change', filterMatters);
            filterMatters();
        });
    </script>
@endsection
