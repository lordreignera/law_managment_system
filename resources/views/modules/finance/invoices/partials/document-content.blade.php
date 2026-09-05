@php
    $client = $invoice->client;
    $matter = $invoice->matter;
    $currency = 'UGX';
    $balance = max((float) $invoice->total - (float) $invoice->paid_amount, 0);
    $lineDescription = $documentType === 'fee-note'
        ? 'Professional fee note for legal services'
        : 'Professional legal services';

    if ($matter) {
        $lineDescription .= ' - '.$matter->reference_no.' '.$matter->title;
    }

    $companyContacts = collect([$company->contact_email, $company->contact_phone])->filter()->implode(' | ');
@endphp

<article class="kfms-finance-document">
    <header class="kfms-finance-document-header">
        <div class="kfms-finance-document-brand">
            @if ($logoSource)
                <img src="{{ $logoSource }}" alt="{{ $company->company_name }} logo">
            @else
                <span>{{ $company->initials }}</span>
            @endif
            <div>
                <strong>{{ $company->company_name }}</strong>
                <em>{{ $company->tagline }}</em>
                @if ($companyContacts)
                    <small>{{ $companyContacts }}</small>
                @endif
            </div>
        </div>

        <div class="kfms-finance-document-title">
            <span>{{ $documentTitle }}</span>
            <strong>{{ $invoice->invoice_no }}</strong>
            <em>{{ $invoice->statusLabel() }}</em>
        </div>
    </header>

    <section class="kfms-finance-document-meta">
        <div>
            <span>Bill To</span>
            <strong>{{ $client?->display_name ?: 'Client' }}</strong>
            @if ($client?->email)
                <p>{{ $client->email }}</p>
            @endif
            @if ($client?->phone)
                <p>{{ $client->phone }}</p>
            @endif
            @if ($client?->address)
                <p>{{ $client->address }}</p>
            @endif
            @if ($client?->tin)
                <p>TIN: {{ $client->tin }}</p>
            @endif
        </div>

        <div>
            <span>Document Details</span>
            <dl>
                <dt>Date</dt>
                <dd>{{ $invoice->invoice_date?->format('d M Y') ?: '-' }}</dd>
                <dt>Due Date</dt>
                <dd>{{ $invoice->due_date?->format('d M Y') ?: '-' }}</dd>
                <dt>Matter</dt>
                <dd>{{ $matter ? $matter->reference_no.' - '.$matter->title : '-' }}</dd>
                <dt>Practice Area</dt>
                <dd>{{ $matter?->practiceArea?->name ?: '-' }}</dd>
            </dl>
        </div>
    </section>

    <table class="kfms-finance-document-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="is-money">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $lineDescription }}</td>
                <td class="is-money">{{ $currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
            @if ((float) $invoice->tax > 0)
                <tr>
                    <td>Tax</td>
                    <td class="is-money">{{ $currency }} {{ number_format((float) $invoice->tax, 2) }}</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <th>Total</th>
                <th class="is-money">{{ $currency }} {{ number_format((float) $invoice->total, 2) }}</th>
            </tr>
            <tr>
                <th>Paid</th>
                <th class="is-money">{{ $currency }} {{ number_format((float) $invoice->paid_amount, 2) }}</th>
            </tr>
            <tr class="is-balance">
                <th>Balance Due</th>
                <th class="is-money">{{ $currency }} {{ number_format($balance, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <section class="kfms-finance-document-note">
        <strong>Payment Note</strong>
        <p>
            Please quote {{ $invoice->invoice_no }} when making payment.
            This {{ strtolower($documentTitle) }} was generated from the firm management system and bears the firm's current branding.
        </p>
    </section>

    <footer class="kfms-finance-document-footer">
        <span>{{ $company->company_name }}</span>
        <span>{{ now()->format('d M Y H:i') }}</span>
    </footer>
</article>
