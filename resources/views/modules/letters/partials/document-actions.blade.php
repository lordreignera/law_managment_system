@can('letters.pdf')
    <button class="kfms-link-btn kfms-letter-icon" type="button" data-letter-print title="Print letter" aria-label="Print letter"><i class="mdi mdi-printer" aria-hidden="true"></i></button>
    <button class="kfms-link-btn kfms-letter-icon" type="button" data-letter-download title="Download PDF" aria-label="Download PDF"><i class="mdi mdi-download" aria-hidden="true"></i></button>
    @can('letters.mark-sent')
        <button class="kfms-link-btn kfms-letter-icon" type="button" data-letter-email-open title="Email letter" aria-label="Email letter"><i class="mdi mdi-email-outline" aria-hidden="true"></i></button>
    @endcan
@endcan
