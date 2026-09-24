<div class="kfms-letter-preview-heading">
    <h2>{{ $previewTitle ?? 'Letter Preview' }}</h2>
    <div class="kfms-toolbar-actions">@include('modules.letters.partials.document-actions')</div>
</div>
<div class="kfms-letter-page-toolbar">
    <span class="kfms-letter-paper-size">A4</span>
    <div class="kfms-letter-page-nav" aria-label="Preview pages">
        <button class="kfms-letter-icon" type="button" data-page-prev title="Previous page" aria-label="Previous page" disabled><i class="mdi mdi-chevron-left" aria-hidden="true"></i></button>
        <label class="kfms-letter-page-number">Page <input type="number" min="1" value="1" data-page-input data-no-money aria-label="Page number" disabled> <span data-page-total>of 1</span></label>
        <button class="kfms-letter-icon" type="button" data-page-next title="Next page" aria-label="Next page" disabled><i class="mdi mdi-chevron-right" aria-hidden="true"></i></button>
    </div>
    <button class="kfms-letter-icon" type="button" data-preview-retry title="Refresh preview" aria-label="Refresh preview"><i class="mdi mdi-refresh" aria-hidden="true"></i></button>
</div>
<p class="kfms-letter-preview-status" data-preview-status role="status">Preparing preview...</p>
<div class="kfms-letter-pages" data-letter-pages aria-label="A4 letter preview" tabindex="0" aria-busy="true">
    <div class="kfms-letter-page-placeholder"></div>
</div>
<noscript><p>The live preview requires JavaScript.</p></noscript>
