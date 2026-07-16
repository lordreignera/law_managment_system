@php
    $selectedTemplateId = old('letter_template_id', $letter->letter_template_id);
    $company = \App\Models\CompanySetting::current();
    $profileSignatureUrl = auth()->user()?->signature_url;
    $savedSignatureUrl = $letter->exists ? $letter->signatureUrl() : null;
@endphp

<form
    class="kfms-form"
    method="POST"
    action="{{ $action }}"
    enctype="multipart/form-data"
    data-letter-form
    data-profile-signature="{{ $profileSignatureUrl }}"
    data-saved-signature="{{ $savedSignatureUrl }}"
>
    @csrf
    @if ($method)
        @method($method)
    @endif
    <input type="hidden" name="letterhead_id" value="{{ old('letterhead_id', $letter->letterhead_id) }}" data-letterhead-id>

    <div class="kfms-letter-builder">
        <div class="kfms-letter-builder-form">
            <div class="kfms-form-grid">
                <label>
            <span>Template</span>
            <select name="letter_template_id" data-letter-template>
                <option value="">Start blank</option>
                @foreach ($templates as $template)
                    <option
                        value="{{ $template->id }}"
                        data-category="{{ $template->category }}"
                        data-subject="{{ e($template->subject) }}"
                        data-body="{{ e($template->body) }}"
                        data-letterhead="{{ $template->letterhead_id }}"
                        @selected((string) $selectedTemplateId === (string) $template->id)
                    >
                        {{ $template->name }}
                    </option>
                @endforeach
            </select>
            @error('letter_template_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Type <b>*</b></span>
            <select name="letter_type" required data-letter-type>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('letter_type', $letter->letter_type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('letter_type') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Reference No.</span>
            <input type="text" name="reference_no" value="{{ old('reference_no', $letter->reference_no) }}" placeholder="Auto-generated if left blank" data-letter-reference>
            @error('reference_no') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Letter Date <b>*</b></span>
            <input type="date" name="letter_date" value="{{ old('letter_date', optional($letter->letter_date)->format('Y-m-d') ?: now()->toDateString()) }}" required data-letter-date>
            @error('letter_date') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Client</span>
            <select name="client_id">
                <option value="">Select client</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) old('client_id', $letter->client_id) === (string) $client->id)>
                        {{ $client->display_name }} {{ $client->email ? '- '.$client->email : '' }}
                    </option>
                @endforeach
            </select>
            @error('client_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Matter</span>
            <select name="matter_id">
                <option value="">Select matter</option>
                @foreach ($matters as $matter)
                    <option value="{{ $matter->id }}" @selected((string) old('matter_id', $letter->matter_id) === (string) $matter->id)>
                        {{ $matter->reference_no }} - {{ $matter->title }}
                    </option>
                @endforeach
            </select>
            @error('matter_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Recovery Account</span>
            <select name="recovery_account_id">
                <option value="">Select recovery account</option>
                @foreach ($recoveries as $recovery)
                    <option value="{{ $recovery->id }}" @selected((string) old('recovery_account_id', $letter->recovery_account_id) === (string) $recovery->id)>
                        {{ $recovery->debtor_name }} {{ $recovery->account_number ? '- '.$recovery->account_number : '' }}
                    </option>
                @endforeach
            </select>
            @error('recovery_account_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Recipient Name <b>*</b></span>
            <input type="text" name="recipient_name" value="{{ old('recipient_name', $letter->recipient_name) }}" required data-letter-recipient>
            @error('recipient_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Recipient Contact</span>
            <input type="text" name="recipient_contact" value="{{ old('recipient_contact', $letter->recipient_contact) }}" data-letter-contact>
            @error('recipient_contact') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Recipient Email</span>
            <input type="email" name="recipient_email" value="{{ old('recipient_email', $letter->recipient_email) }}" data-letter-email>
            @error('recipient_email') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
            <span>Recipient Address</span>
            <textarea name="recipient_address" rows="3" data-letter-address>{{ old('recipient_address', $letter->recipient_address) }}</textarea>
            @error('recipient_address') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
            <span>Subject <b>*</b></span>
            <input type="text" name="subject" value="{{ old('subject', $letter->subject) }}" required data-letter-subject>
            @error('subject') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
            <span>Body <b>*</b></span>
            <textarea name="body" rows="14" required data-letter-body>{{ old('body', $letter->body) }}</textarea>
            @error('body') <small>{{ $message }}</small> @enderror
                </label>

                <label>
            <span>Signature</span>
            <select name="signature_mode" data-signature-mode>
                @foreach ($signatureModes as $value => $label)
                    <option value="{{ $value }}" @selected(old('signature_mode', $letter->signature_mode) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if (! auth()->user()->signature_path)
                <small>No profile signature is uploaded yet.</small>
            @endif
            @error('signature_mode') <small>{{ $message }}</small> @enderror
                </label>

                <label data-signature-upload style="display: none;">
            <span>Upload Signature</span>
            <input type="file" name="signature_upload" accept="image/png,image/jpeg,image/webp" data-signature-upload-input>
            @error('signature_upload') <small>{{ $message }}</small> @enderror
                </label>

                <div class="kfms-span-2" data-signature-draw style="display: none;">
            <span class="kfms-form-label">Draw Signature</span>
            <canvas class="kfms-signature-pad" width="640" height="160" style="width: 100%; height: 160px; border: 1px solid #d8e2ef; border-radius: 8px; background: #fff;"></canvas>
            <input type="hidden" name="drawn_signature" data-drawn-signature>
            <button class="kfms-link-btn mt-2" type="button" data-clear-signature>Clear Signature</button>
            @error('drawn_signature') <small>{{ $message }}</small> @enderror
                </div>

                <label class="kfms-span-2">
            <span>Supporting Documents</span>
            <input type="file" name="documents[]" multiple>
            @error('documents') <small>{{ $message }}</small> @enderror
            @error('documents.*') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Letter
                </button>
            </div>
        </div>

        <aside class="kfms-letter-builder-preview">
            <div class="kfms-panel-header">
                <div>
                    <h2>Live Preview</h2>
                    <span>Updates as you draft, sign, and change template wording.</span>
                </div>
            </div>
            <article class="kfms-letter-preview kfms-letter-preview-live">
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
                    <span data-preview-reference>{{ old('reference_no', $letter->reference_no) ?: 'Auto reference' }}</span>
                    <span data-preview-date>{{ old('letter_date', optional($letter->letter_date)->format('Y-m-d') ?: now()->toDateString()) }}</span>
                </div>

                <div class="kfms-letter-recipient">
                    <strong data-preview-recipient>{{ old('recipient_name', $letter->recipient_name) ?: 'Recipient name' }}</strong>
                    <span data-preview-contact>{{ old('recipient_contact', $letter->recipient_contact) }}</span>
                    <span data-preview-email>{{ old('recipient_email', $letter->recipient_email) }}</span>
                    <span data-preview-address>{{ old('recipient_address', $letter->recipient_address) }}</span>
                </div>

                <h3 data-preview-subject>{{ old('subject', $letter->subject) ?: 'Letter subject' }}</h3>
                <div class="kfms-letter-body" data-preview-body>{{ old('body', $letter->body) ?: 'Start typing the letter body to preview the final document.' }}</div>

                <footer class="kfms-letter-signature">
                    <img data-preview-signature src="{{ $savedSignatureUrl ?: ($letter->signature_mode === 'profile' ? $profileSignatureUrl : '') }}" alt="Signature" @if (! ($savedSignatureUrl ?: ($letter->signature_mode === 'profile' ? $profileSignatureUrl : ''))) hidden @endif>
                    <strong>For: {{ $company->company_name }}</strong>
                    <span>cc. Client.</span>
                </footer>
            </article>
        </aside>
    </div>
</form>

@push('scripts')
    <script>
        document.querySelectorAll('[data-letter-form]').forEach((form) => {
            const template = form.querySelector('[data-letter-template]');
            const type = form.querySelector('[data-letter-type]');
            const subject = form.querySelector('[data-letter-subject]');
            const body = form.querySelector('[data-letter-body]');
            const letterhead = form.querySelector('[data-letterhead-id]');
            const reference = form.querySelector('[data-letter-reference]');
            const letterDate = form.querySelector('[data-letter-date]');
            const recipient = form.querySelector('[data-letter-recipient]');
            const contact = form.querySelector('[data-letter-contact]');
            const email = form.querySelector('[data-letter-email]');
            const address = form.querySelector('[data-letter-address]');
            const signatureMode = form.querySelector('[data-signature-mode]');
            const signatureInput = form.querySelector('[data-signature-upload-input]');
            const uploadBox = form.querySelector('[data-signature-upload]');
            const drawBox = form.querySelector('[data-signature-draw]');
            const canvas = form.querySelector('.kfms-signature-pad');
            const hiddenSignature = form.querySelector('[data-drawn-signature]');
            const previewReference = form.querySelector('[data-preview-reference]');
            const previewDate = form.querySelector('[data-preview-date]');
            const previewRecipient = form.querySelector('[data-preview-recipient]');
            const previewContact = form.querySelector('[data-preview-contact]');
            const previewEmail = form.querySelector('[data-preview-email]');
            const previewAddress = form.querySelector('[data-preview-address]');
            const previewSubject = form.querySelector('[data-preview-subject]');
            const previewBody = form.querySelector('[data-preview-body]');
            const previewSignature = form.querySelector('[data-preview-signature]');

            const syncText = () => {
                previewReference.textContent = reference.value || 'Auto reference';
                previewDate.textContent = letterDate.value || 'Letter date';
                previewRecipient.textContent = recipient.value || 'Recipient name';
                previewContact.textContent = contact.value || '';
                previewEmail.textContent = email.value || '';
                previewAddress.textContent = address.value || '';
                previewSubject.textContent = subject.value || 'Letter subject';
                previewBody.textContent = body.value || 'Start typing the letter body to preview the final document.';
            };

            [reference, letterDate, recipient, contact, email, address, subject, body].forEach((input) => {
                input?.addEventListener('input', syncText);
                input?.addEventListener('change', syncText);
            });

            template?.addEventListener('change', () => {
                const selected = template.selectedOptions[0];
                if (! selected?.value) return;

                type.value = selected.dataset.category || type.value;
                subject.value = selected.dataset.subject || subject.value;
                body.value = selected.dataset.body || body.value;
                letterhead.value = selected.dataset.letterhead || letterhead.value;
                syncText();
            });

            const setSignaturePreview = (src) => {
                if (! previewSignature) return;

                if (src) {
                    previewSignature.src = src;
                    previewSignature.hidden = false;
                } else {
                    previewSignature.removeAttribute('src');
                    previewSignature.hidden = true;
                }
            };

            const syncSignatureMode = () => {
                uploadBox.style.display = signatureMode.value === 'upload' ? '' : 'none';
                drawBox.style.display = signatureMode.value === 'drawn' ? '' : 'none';

                if (signatureMode.value === 'profile') {
                    setSignaturePreview(form.dataset.profileSignature || '');
                }

                if (signatureMode.value === 'none') {
                    setSignaturePreview('');
                }

                if (signatureMode.value === 'upload' && signatureInput?.files?.[0]) {
                    setSignaturePreview(URL.createObjectURL(signatureInput.files[0]));
                }

                if (signatureMode.value === 'drawn' && hiddenSignature?.value) {
                    setSignaturePreview(hiddenSignature.value);
                }
            };
            signatureMode?.addEventListener('change', syncSignatureMode);
            signatureInput?.addEventListener('change', () => {
                if (signatureInput.files?.[0]) {
                    setSignaturePreview(URL.createObjectURL(signatureInput.files[0]));
                }
            });
            syncSignatureMode();
            syncText();

            if (canvas && hiddenSignature) {
                const ctx = canvas.getContext('2d');
                let drawing = false;

                const point = (event) => {
                    const rect = canvas.getBoundingClientRect();
                    const touch = event.touches ? event.touches[0] : event;
                    return {
                        x: (touch.clientX - rect.left) * (canvas.width / rect.width),
                        y: (touch.clientY - rect.top) * (canvas.height / rect.height),
                    };
                };

                const start = (event) => {
                    drawing = true;
                    const p = point(event);
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                    event.preventDefault();
                };

                const move = (event) => {
                    if (! drawing) return;
                    const p = point(event);
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#031b3c';
                    ctx.lineTo(p.x, p.y);
                    ctx.stroke();
                    hiddenSignature.value = canvas.toDataURL('image/png');
                    event.preventDefault();
                };

                const end = () => {
                    drawing = false;
                    hiddenSignature.value = canvas.toDataURL('image/png');
                    if (signatureMode.value === 'drawn') {
                        setSignaturePreview(hiddenSignature.value);
                    }
                };

                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                canvas.addEventListener('mouseup', end);
                canvas.addEventListener('mouseleave', end);
                canvas.addEventListener('touchstart', start, { passive: false });
                canvas.addEventListener('touchmove', move, { passive: false });
                canvas.addEventListener('touchend', end);

                form.querySelector('[data-clear-signature]')?.addEventListener('click', () => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hiddenSignature.value = '';
                    if (signatureMode.value === 'drawn') {
                        setSignaturePreview('');
                    }
                });
            }
        });
    </script>
@endpush
