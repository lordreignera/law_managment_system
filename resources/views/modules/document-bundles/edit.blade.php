@extends('layouts.admin')

@section('title', 'Edit Document Bundle')
@section('page-title', 'Edit Document Bundle')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div><h2>Edit Bundle</h2><span>{{ $matter->reference_no }} - arrange documents in bundle order.</span></div>
            <a class="kfms-link-btn" href="{{ route('matters.bundles.show', [$matter, $bundle]) }}">Back to Bundle</a>
        </div>
        <form class="kfms-form" method="POST" action="{{ route('matters.bundles.update', [$matter, $bundle]) }}">
            @csrf @method('PUT')
            <div class="kfms-form-grid">
                <label><span>Bundle Name</span><input type="text" name="name" value="{{ old('name', $bundle->name) }}" required></label>
                <label><span>Bundle Type</span><select name="bundle_type" required>@foreach ($bundleTypes as $value => $label)<option value="{{ $value }}" @selected(old('bundle_type', $bundle->bundle_type) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label><span>Status</span><select name="status" required>@foreach (\App\Models\DocumentBundle::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', $bundle->status) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="kfms-span-2"><span>Description</span><textarea name="description" rows="3">{{ old('description', $bundle->description) }}</textarea></label>
            </div>
            @php($selected = old('attachment_ids', $bundle->items->sortBy('sort_order')->pluck('attachment_id')->all()))
            @php($sortOrders = old('sort_orders', $bundle->items->pluck('sort_order', 'attachment_id')->all()))
            @include('modules.document-bundles.partials.attachment-picker', [
                'attachments' => $attachments,
                'selected' => $selected,
                'sortOrders' => $sortOrders,
                'label' => 'Documents in Bundle',
                'placeholder' => 'Select documents for this bundle',
            ])
            <div class="kfms-form-actions"><a class="kfms-link-btn" href="{{ route('matters.bundles.show', [$matter, $bundle]) }}">Cancel</a><button class="kfms-btn" type="submit">Save Bundle</button></div>
        </form>
    </section>
@endsection
