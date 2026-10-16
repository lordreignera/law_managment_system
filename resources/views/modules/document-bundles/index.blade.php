@extends('layouts.admin')

@section('title', 'Document Bundles')
@section('page-title', 'Document Bundles')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $matter->reference_no }} - Document Bundles</h2>
                <span>{{ $matter->title }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('matters.show', $matter) }}">Back to Matter</a>
                @can('matters.bundles.create')
                    <a class="kfms-btn" href="{{ route('matters.bundles.create', $matter) }}"><i class="mdi mdi-package-variant-closed"></i> Create Bundle</a>
                @endcan
            </div>
        </div>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Created By</th><th>Documents</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($bundles as $bundle)
                        <tr>
                            <td>{{ $bundle->name }}</td>
                            <td>{{ str($bundle->bundle_type)->headline() }}</td>
                            <td>{{ $bundle->statusLabel() }}</td>
                            <td>{{ $bundle->creator?->name ?: '-' }}</td>
                            <td>{{ $bundle->items()->count() }}</td>
                            <td><a class="kfms-link-btn" href="{{ route('matters.bundles.show', [$matter, $bundle]) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="kfms-empty">No document bundles created for this matter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
