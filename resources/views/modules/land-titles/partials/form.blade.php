@csrf
@php
    $selectedBankId = (string) old('bank_id', $title->bank_id);
    $selectedBankBranchId = (string) old('bank_branch_id', $title->bank_branch_id);
    $bankBranchOptions = $bankBranches
        ->map(fn ($branch) => [
            'id' => (string) $branch->id,
            'bank_id' => (string) $branch->bank_id,
            'label' => trim(($branch->bank?->name ? $branch->bank->name.' - ' : '').$branch->name.($branch->office_location ? ' ('.$branch->office_location.')' : '')),
        ])
        ->values();
@endphp

<div class="kfms-form-grid">
    <label>
        <span>Reference Number</span>
        <input type="text" value="{{ old('reference_no', $title->reference_no) }}" disabled>
    </label>

    <label>
        <span>Status <span class="kfms-required">*</span></span>
        <select name="status" required>
            @foreach ($statuses as $value => $label)
                @continue($value === 'returned' && ($title->status ?? null) !== 'returned')
                <option value="{{ $value }}" @selected(old('status', $title->status ?? 'pending') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Borrower Name <span class="kfms-required">*</span></span>
        <input type="text" name="borrower_name" value="{{ old('borrower_name', $title->borrower_name) }}" required>
        @error('borrower_name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Instruction Type</span>
        <input type="text" name="instruction_type" value="{{ old('instruction_type', $title->instruction_type) }}" placeholder="Mortgage, release, custody, transfer">
        @error('instruction_type') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Bank / Financial Institution</span>
        <select name="bank_id" data-bank-select>
            <option value="">Select bank or institution</option>
            @foreach ($banks as $bank)
                <option value="{{ $bank->id }}" @selected($selectedBankId === (string) $bank->id)>{{ $bank->name }}</option>
            @endforeach
        </select>
        @error('bank_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Bank Branch / Source Office</span>
        <select name="bank_branch_id" data-bank-branch-select data-selected-branch="{{ $selectedBankBranchId }}">
            <option value="">{{ $selectedBankId ? 'Select branch or office' : 'Select bank first' }}</option>
        </select>
        @error('bank_branch_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>MZO / Zonal Office</span>
        <select name="zonal_office_id">
            <option value="">Select zonal office</option>
            @foreach ($zonalOffices as $office)
                <option value="{{ $office->id }}" @selected((string) old('zonal_office_id', $title->zonal_office_id) === (string) $office->id)>
                    {{ $office->name }}{{ $office->office_location ? ' - '.$office->office_location : '' }}
                </option>
            @endforeach
        </select>
        @error('zonal_office_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Linked Matter</span>
        <select name="matter_id">
            <option value="">No linked matter</option>
            @foreach ($matters as $matter)
                <option value="{{ $matter->id }}" @selected((string) old('matter_id', $title->matter_id) === (string) $matter->id)>
                    {{ $matter->reference_no }} - {{ $matter->title }}
                </option>
            @endforeach
        </select>
        @error('matter_id') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Handled By</span>
        <select name="handled_by">
            <option value="">Unassigned</option>
            @foreach ($handlers as $handler)
                <option value="{{ $handler->id }}" @selected((string) old('handled_by', $title->handled_by) === (string) $handler->id)>{{ $handler->name }}</option>
            @endforeach
        </select>
        @error('handled_by') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Received From</span>
        <input type="text" name="received_from" value="{{ old('received_from', $title->received_from) }}" placeholder="Bank branch, officer, or institution contact">
        @error('received_from') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Instruction Date</span>
        <input type="date" name="instruction_date" value="{{ old('instruction_date', $title->instruction_date?->toDateString()) }}">
        @error('instruction_date') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Date &amp; Time Received</span>
        <input type="datetime-local" name="received_at" value="{{ old('received_at', $title->received_at?->format('Y-m-d\TH:i')) }}">
        @error('received_at') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Date &amp; Time Dispatched</span>
        <input type="datetime-local" name="dispatched_at" value="{{ old('dispatched_at', $title->dispatched_at?->format('Y-m-d\TH:i')) }}">
        @error('dispatched_at') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Upload Document</span>
        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
        @error('documents') <small>{{ $message }}</small> @enderror
        @error('documents.*') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-span-2">
        <span>Notes</span>
        <textarea name="notes" rows="4">{{ old('notes', $title->notes) }}</textarea>
        @error('notes') <small>{{ $message }}</small> @enderror
    </label>
</div>

<div class="kfms-form-actions">
    <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">Cancel</a>
    <button type="submit">{{ $buttonText }}</button>
</div>

@push('scripts')
    <script>
        window.kfmsBankBranches = @json($bankBranchOptions);

        document.querySelectorAll('[data-bank-select]').forEach((bankSelect) => {
            const form = bankSelect.closest('form');
            const branchSelect = form?.querySelector('[data-bank-branch-select]');

            if (! branchSelect) {
                return;
            }

            const populateBranches = () => {
                const bankId = bankSelect.value;
                const selectedBranch = branchSelect.dataset.selectedBranch || '';
                const branches = (window.kfmsBankBranches || []).filter((branch) => branch.bank_id === bankId);

                branchSelect.innerHTML = '';
                branchSelect.append(new Option(bankId ? 'Select branch or office' : 'Select bank first', ''));
                branchSelect.disabled = ! bankId;

                branches.forEach((branch) => {
                    const option = new Option(branch.label, branch.id);
                    option.selected = branch.id === selectedBranch;
                    branchSelect.append(option);
                });

                if (! branches.some((branch) => branch.id === selectedBranch)) {
                    branchSelect.value = '';
                }
            };

            bankSelect.addEventListener('change', () => {
                branchSelect.dataset.selectedBranch = '';
                populateBranches();
            });

            populateBranches();
        });
    </script>
@endpush
