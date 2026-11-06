@extends('layouts.admin')

@section('title', 'Audit Details')
@section('page-title', 'Audit Details')

@section('content')
    @php
        $recordType = \App\Http\Controllers\AuditLogController::recordLabel($auditLog->auditable_type);
        $formatValues = fn (array $values) => json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    @endphp

    <section class="kfms-panel kfms-audit-panel">
        <div class="kfms-panel-header">
            <div>
                <span class="kfms-muted-text">Audit record #{{ $auditLog->id }}</span>
                <h2>{{ \Illuminate\Support\Str::headline(str_replace(['_', '-'], ' ', $auditLog->event)) }} {{ $recordType }}</h2>
                <span>{{ $auditLog->created_at?->format('d M Y, H:i:s') }} · {{ $auditLog->user?->name ?: 'System' }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('audit.index') }}"><i class="mdi mdi-arrow-left"></i> Back to audit log</a>
        </div>

        <div class="kfms-audit-context">
            <div><span>Actor</span><strong>{{ $auditLog->user?->name ?: 'System' }}</strong><small>{{ $auditLog->user?->email ?: 'Automated action' }}</small></div>
            <div><span>Record</span><strong>{{ $recordType }}</strong><small>{{ $auditLog->auditable_id ? 'ID #'.$auditLog->auditable_id : 'No record reference' }}</small></div>
            <div><span>IP address</span><strong>{{ $auditLog->ip_address ?: 'Not recorded' }}</strong><small>{{ $auditLog->user_agent ? \Illuminate\Support\Str::limit($auditLog->user_agent, 90) : 'No user agent recorded' }}</small></div>
            <div><span>Request URL</span><strong>{{ $auditLog->url ? \Illuminate\Support\Str::limit(parse_url($auditLog->url, PHP_URL_PATH) ?: $auditLog->url, 90) : 'Not recorded' }}</strong><small>Captured when the event was written</small></div>
        </div>

        <div class="kfms-audit-values">
            <article>
                <div class="kfms-audit-values-header"><h3>Before</h3><span>{{ count($oldValues) }} fields</span></div>
                <pre>{{ $formatValues($oldValues) }}</pre>
            </article>
            <article>
                <div class="kfms-audit-values-header"><h3>After</h3><span>{{ count($newValues) }} fields</span></div>
                <pre>{{ $formatValues($newValues) }}</pre>
            </article>
        </div>

        <p class="kfms-audit-note"><i class="mdi mdi-information-outline"></i> Sensitive values such as passwords, tokens, private keys, cookies, and signatures are hidden in this view. The stored audit record is not changed.</p>
    </section>
@endsection
