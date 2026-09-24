<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 18mm 18mm 22mm; }
        body { margin: 0; color: #061a32; font-family: DejaVu Sans, sans-serif; font-size: 11pt; line-height: 1.5; overflow-wrap: break-word; }
        .head { border-bottom: 2px solid #c99428; display: table; margin-bottom: 28px; padding-bottom: 12px; width: 100%; }
        .head img { display: table-cell; max-height: 68px; max-width: 190px; vertical-align: middle; }
        .head div { display: table-cell; text-align: right; vertical-align: middle; }
        .head strong { display: block; font-size: 18px; }
        .meta { display: table; margin-bottom: 24px; width: 100%; }
        .meta span { display: table-cell; width: 50%; }
        .meta span:last-child { text-align: right; }
        .recipient { margin-bottom: 22px; }
        .recipient div { white-space: pre-line; }
        h1 { font-size: 14px; margin: 20px 0; text-decoration: underline; text-transform: uppercase; }
        .body { white-space: pre-line; }
        .signature { margin-top: 28px; page-break-inside: avoid; }
        .signature img { display: block; max-height: 70px; max-width: 180px; }
        .footer { border-top: 1px solid #c99428; bottom: -12mm; color: #53657c; font-size: 9px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <header class="head">
        @if ($company->logo_public_path)
            <img src="{{ $company->logo_public_path }}" alt="Logo">
        @endif
        <div>
            <strong>{{ $company->company_name }}</strong>
            <span>{{ $company->tagline }}</span>
        </div>
    </header>

    <div class="meta">
        <span>{{ $letter->reference_no }}</span>
        <span>{{ $letter->letter_date?->format('jS F Y') }}</span>
    </div>

    <div class="recipient">
        <strong>{{ $letter->recipient_name }}</strong>
        @if ($letter->recipient_contact)<div>{{ $letter->recipient_contact }}</div>@endif
        @if ($letter->recipient_email)<div>{{ $letter->recipient_email }}</div>@endif
        @if ($letter->recipient_address)<div>{{ $letter->recipient_address }}</div>@endif
    </div>

    <h1>{{ $letter->subject }}</h1>
    <div class="body">{{ $letter->renderedBody() }}</div>

    <div class="signature">
        @if ($signatureImage ?? null)
            <img src="{{ $signatureImage }}" alt="Signature">
        @elseif (! isset($signatureImage) && $letter->signature_path && file_exists(storage_path('app/public/'.$letter->signature_path)))
            <img src="{{ storage_path('app/public/'.$letter->signature_path) }}" alt="Signature">
        @endif
        <strong>For: {{ $company->company_name }}</strong><br>
        <span>cc. Client.</span>
    </div>

    <div class="footer">
        {{ $company->contact_email }} {{ $company->contact_phone }}
    </div>
</body>
</html>
