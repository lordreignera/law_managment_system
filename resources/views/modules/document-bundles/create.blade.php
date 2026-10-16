@extends('layouts.admin')

@section('title', 'Create Document Bundle')
@section('page-title', 'Create Document Bundle')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Create Bundle</h2>
                <span>{{ $matter->reference_no }} - select current documents in the order they should appear.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('matters.bundles.index', $matter) }}">Back to Bundles</a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('matters.bundles.store', $matter) }}">
            @csrf
            <div class="kfms-form-grid">
                <label><span>Bundle Name</span><input type="text" name="name" value="{{ old('name') }}" required>@error('name')<small>{{ $message }}</small>@enderror</label>
                <label><span>Bundle Type</span><select name="bundle_type" required>@foreach ($bundleTypes as $value => $label)<option value="{{ $value }}" @selected(old('bundle_type', 'pleadings') === $value)>{{ $label }}</option>@endforeach</select>@error('bundle_type')<small>{{ $message }}</small>@enderror</label>
                <label><span>Status</span><select name="status" required>@foreach (\App\Models\DocumentBundle::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="kfms-span-2"><span>Description</span><textarea name="description" rows="3">{{ old('description') }}</textarea></label>
            </div>

            @include('modules.document-bundles.partials.attachment-picker', [
                'attachments' => $attachments,
                'selected' => old('attachment_ids', []),
                'sortOrders' => old('sort_orders', []),
                'label' => 'Documents in Bundle',
                'placeholder' => 'Select documents for this bundle',
            ])

            <div class="kfms-form-actions"><a class="kfms-link-btn" href="{{ route('matters.bundles.index', $matter) }}">Cancel</a><button class="kfms-btn" type="submit">Create Bundle</button></div>
        </form>
    </section>
@endsection
