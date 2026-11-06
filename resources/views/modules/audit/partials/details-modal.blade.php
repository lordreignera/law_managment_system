@php
    $recordType = \App\Http\Controllers\AuditLogController::recordLabel($auditLog->auditable_type);
    $oldValues = $auditLog->safeValues($auditLog->old_values);
    $newValues = $auditLog->safeValues($auditLog->new_values);
    $formatValues = fn (array $values) => json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
@endphp

<div class="modal fade kfms-modal kfms-audit-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="kfms-muted-text">Audit record #{{ $auditLog->id }}</span>
                    <h5 class="modal-title" id="{{ $modalId }}-label">{{ \Illuminate\Support\Str::headline(str_replace(['_', '-'], ' ', $auditLog->event)) }} {{ $recordType }}</h5>
                    <span>{{ $auditLog->created_at?->format('d M Y, H:i:s') }} · {{ $auditLog->user?->name ?: 'System' }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
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

                <p class="kfms-audit-note"><i class="mdi mdi-information-outline"></i> Sensitive values such as passwords, tokens, private keys, cookies, and signatures are hidden in this view.</p>
            </div>

            <div class="modal-footer">
                <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
