@php $branch = $branch ?? null; @endphp

<div class="kfms-form-grid">
    <label>
        <span>Branch Code</span>
        <input type="text" name="code" value="{{ old('code', $branch?->code ?? ($code ?? '')) }}" maxlength="30" required>
        @error('code') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>Branch Name</span>
        <input type="text" name="name" value="{{ old('name', $branch?->name) }}" maxlength="191" required>
        @error('name') <small>{{ $message }}</small> @enderror
    </label>

    <label>
        <span>City</span>
        <input type="text" name="city" value="{{ old('city', $branch?->city) }}" maxlength="191">
        @error('city') <small>{{ $message }}</small> @enderror
    </label>

    <label class="kfms-checkbox">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch?->is_active ?? true))>
        <span>Active</span>
    </label>

    <label class="kfms-span-2">
        <span>Address</span>
        <textarea name="address" rows="3" maxlength="1000">{{ old('address', $branch?->address) }}</textarea>
        @error('address') <small>{{ $message }}</small> @enderror
    </label>
</div>
