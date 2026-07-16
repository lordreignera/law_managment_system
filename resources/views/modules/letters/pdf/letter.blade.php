<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 36px; }
        body { color: #061a32; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.55; }
        .head { border-bottom: 2px solid #c99428; display: table; margin-bottom: 28px; padding-bottom: 12px; width: 100%; }
        .head img { display: table-cell; max-height: 68px; max-width: 190px; vertical-align: middle; }
        .head div { display: table-cell; text-align: right; vertical-align: middle; }
        .head strong { display: block; font-size: 18px; }
        .meta { display: table; margin-bottom: 24px; width: 100%; }
        .meta span { display: table-cell; width: 50%; }
        .meta span:last-child { text-align: right; }
        .recipient { margin-bottom: 22px; white-space: pre-line; }
        h1 { font-size: 14px; margin: 20px 0; text-decoration: underline; text-transform: uppercase; }
        .body { white-space: pre-line; }
        .signature { margin-top: 38px; }
        .signature img { display: block; max-height: 70px; max-width: 180px; }
        .footer { border-top: 1px solid #c99428; bottom: 0; color: #53657c; font-size: 10px; left: 36px; position: fixed; right: 36px; text-align: center; }
    </style>
</head>
<body>
    <header class="head">
        @if ($company->logo_url)
            <img src="{{ public_path(ltrim(parse_url($company->logo_url, PHP_URL_PATH) ?: '', '/')) }}" alt="Logo">
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
        {{ $letter->recipient_name }}
        @if ($letter->recipient_contact)
{{ $letter->recipient_contact }}
        @endif
        @if ($letter->recipient_email)
{{ $letter->recipient_email }}
        @endif
        @if ($letter->recipient_address)
{{ $letter->recipient_address }}
        @endif
    </div>

    <h1>{{ $letter->subject }}</h1>
    <div class="body">{{ $letter->renderedBody() }}</div>

    <div class="signature">
        @if ($letter->signature_path && file_exists(storage_path('app/public/'.$letter->signature_path)))
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
