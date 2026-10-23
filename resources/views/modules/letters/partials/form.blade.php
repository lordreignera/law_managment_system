@php
    $selectedTemplateId = old('letter_template_id', $letter->letter_template_id);
@endphp

<form
    class="kfms-form kfms-letter-form" id="letter-editor"
    method="POST"
    action="{{ $action }}"
    enctype="multipart/form-data"
    data-letter-form
    data-preview-url="{{ $letter->exists ? route('letters.preview', $letter) : route('letters.draft-preview') }}"
    data-email-url="{{ $letter->exists ? route('letters.email', $letter) : '' }}"
    data-saved="{{ $letter->exists ? 'true' : 'false' }}"
>
    @csrf
    @if ($method)
        @method($method)
    @endif
    <input type="hidden" name="letterhead_id" value="{{ old('letterhead_id', $letter->letterhead_id) }}" data-letterhead-id>

    <div class="kfms-letter-actionbar">
        <div class="kfms-toolbar-actions">
            <button class="kfms-btn" type="submit"><i class="mdi mdi-content-save" aria-hidden="true"></i> {{ $letter->status === 'draft' ? 'Save Draft' : 'Save Changes' }}</button>
        </div>
        <span data-save-status role="status">{{ $letter->exists ? 'Saved' : 'Unsaved draft' }}</span>
    </div>
    @if ($letter->status === 'draft')
        <p class="kfms-letter-draft-hint">Save a draft at any time. Complete the recipient, subject, date, and letter content before submitting it for review.</p>
    @endif
    <div class="kfms-alert kfms-alert-danger" data-letter-error role="alert" hidden></div>
    @if ($errors->any())
        <div class="kfms-alert kfms-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="kfms-letter-builder">
        <div class="kfms-letter-builder-form">
            <div class="kfms-form-grid kfms-letter-fields">
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
            <span>Letter Date @if ($letter->status !== 'draft')<b>*</b>@endif</span>
            <input type="date" name="letter_date" value="{{ old('letter_date', optional($letter->letter_date)->format('Y-m-d') ?: now()->toDateString()) }}" @if ($letter->status !== 'draft') required @endif data-letter-date>
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
            <span>Recipient Name @if ($letter->status !== 'draft')<b>*</b>@endif</span>
            <input type="text" name="recipient_name" value="{{ old('recipient_name', $letter->recipient_name) }}" @if ($letter->status !== 'draft') required @endif data-letter-recipient>
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
            <textarea name="recipient_address" rows="2" data-letter-address>{{ old('recipient_address', $letter->recipient_address) }}</textarea>
            @error('recipient_address') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
            <span>Subject @if ($letter->status !== 'draft')<b>*</b>@endif</span>
            <input type="text" name="subject" value="{{ old('subject', $letter->subject) }}" @if ($letter->status !== 'draft') required @endif data-letter-subject>
            @error('subject') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
            <span>Body @if ($letter->status !== 'draft')<b>*</b>@endif</span>
            <textarea name="body" rows="10" @if ($letter->status !== 'draft') required @endif data-letter-body>{{ old('body', $letter->body) }}</textarea>
            @error('body') <small>{{ $message }}</small> @enderror
            <span class="kfms-letter-body-status">
                <span data-page-summary role="status">A4</span>
                <button class="kfms-letter-icon" type="button" data-jump-last title="Go to last page" aria-label="Go to last page"><i class="mdi mdi-page-last" aria-hidden="true"></i></button>
            </span>
                </label>

                <details class="kfms-letter-optional-fields" @if ($errors->has('signature_upload') || $errors->has('drawn_signature') || $errors->has('documents') || $errors->has('documents.*')) open @endif>
                    <summary>Signature and supporting documents <span>Optional</span></summary>
                    <div class="kfms-letter-optional-grid">
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
            <input type="hidden" name="drawn_signature" value="{{ old('drawn_signature') }}" data-drawn-signature>
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

                </details>

            </div>
        </div>

        <aside class="kfms-letter-builder-preview">
            @include('modules.letters.partials.document-preview', ['previewTitle' => 'Live Preview'])
        </aside>
    </div>
</form>

@include('modules.letters.partials.email-dialog', ['editor' => true])

@push('scripts')
    @vite('resources/js/letter-workspace.js')
@endpush
