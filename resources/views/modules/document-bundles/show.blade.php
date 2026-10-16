@extends('layouts.admin')

@section('title', $bundle->name)
@section('page-title', 'Document Bundle')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div><h2>{{ $bundle->name }}</h2><span>{{ $matter->reference_no }} · {{ $bundle->statusLabel() }}</span></div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('matters.bundles.index', $matter) }}">Back to Bundles</a>
                @can('matters.bundles.edit')<a class="kfms-link-btn" href="{{ route('matters.bundles.edit', [$matter, $bundle]) }}">Edit Order</a>@endcan
                @can('matters.bundles.download')<a class="kfms-btn" href="{{ route('matters.bundles.download', [$matter, $bundle]) }}"><i class="mdi mdi-download"></i> Download ZIP</a>@endcan
            </div>
        </div>
        @if ($bundle->description)<p class="kfms-muted-text">{{ $bundle->description }}</p>@endif
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Order</th><th>Document</th><th>Category</th><th>Version</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($bundle->items as $item)
                        <tr>
                            <td>{{ $item->sort_order }}</td>
                            <td>{{ $item->label ?: $item->attachment?->title ?: $item->attachment?->original_name }}</td>
                            <td>{{ str($item->attachment?->category ?: 'document')->headline() }}</td>
                            <td>v{{ $item->attachment?->version_number ?: 1 }}</td>
                            <td>@if ($item->attachment)<a class="kfms-link-btn" href="{{ route('attachments.download', $item->attachment) }}">Download</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="kfms-empty">This bundle has no documents.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
