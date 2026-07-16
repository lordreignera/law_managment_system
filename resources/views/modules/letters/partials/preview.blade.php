@php
    $company = \App\Models\CompanySetting::current();
@endphp

<article class="kfms-letter-preview">
    <header class="kfms-letter-head">
        @if ($company->logo_url)
            <img src="{{ $company->logo_url }}" alt="{{ $company->company_name }} logo">
        @endif
        <div>
            <strong>{{ $company->company_name }}</strong>
            <span>{{ $company->tagline }}</span>
        </div>
    </header>

    <div class="kfms-letter-meta">
        <span>{{ $letter->reference_no }}</span>
        <span>{{ $letter->letter_date?->format('jS F Y') }}</span>
    </div>

    <div class="kfms-letter-recipient">
        <strong>{{ $letter->recipient_name }}</strong>
        @if ($letter->recipient_contact)<span>{{ $letter->recipient_contact }}</span>@endif
        @if ($letter->recipient_email)<span>{{ $letter->recipient_email }}</span>@endif
        @if ($letter->recipient_address)<span>{!! nl2br(e($letter->recipient_address)) !!}</span>@endif
    </div>

    <h3>{{ $letter->subject }}</h3>
    <div class="kfms-letter-body">{!! nl2br(e($letter->renderedBody())) !!}</div>

    <footer class="kfms-letter-signature">
        @if ($letter->signatureUrl())
            <img src="{{ $letter->signatureUrl() }}" alt="Signature">
        @endif
        <strong>For: {{ $company->company_name }}</strong>
        <span>cc. Client.</span>
    </footer>
</article>
