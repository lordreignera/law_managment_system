@can('letters.pdf')
    @can('letters.mark-sent')
        @push('modals')
            <dialog class="kfms-letter-email-dialog" data-letter-email-dialog aria-labelledby="letter-email-title">
                <form class="kfms-form" data-letter-email-form>
                    <div class="kfms-letter-preview-heading">
                        <h2 id="letter-email-title">Email Letter</h2>
                        <button class="kfms-letter-icon" type="button" data-email-close title="Close" aria-label="Close email"><i class="mdi mdi-close" aria-hidden="true"></i></button>
                    </div>
                    <div class="kfms-alert kfms-alert-danger" data-email-error role="alert" hidden></div>
                    <label><span>To <b>*</b></span><input name="to" type="email" value="{{ $letter->recipient_email }}" maxlength="191" required></label>
                    <label><span>Subject <b>*</b></span><input name="subject" value="{{ $letter->subject }}" maxlength="255" required></label>
                    <label><span>Message <b>*</b></span><textarea name="message" rows="5" maxlength="5000" required>Please find the attached letter.</textarea></label>
                    <div class="kfms-letter-email-attachment"><i class="mdi mdi-file-pdf-box" aria-hidden="true"></i><span data-email-attachment>{{ $letter->reference_no ?: 'Letter' }}.pdf</span></div>
                    <div class="kfms-form-actions">
                        <button class="kfms-link-btn" type="button" data-email-close>Cancel</button>
                        <button class="kfms-btn" type="submit" data-email-send><i class="mdi mdi-email-outline" aria-hidden="true"></i> {{ ($editor ?? false) ? 'Save & Send Email' : 'Send Email' }}</button>
                    </div>
                    <span data-email-status role="status"></span>
                </form>
            </dialog>
        @endpush
    @endcan
@endcan
