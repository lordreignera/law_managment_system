@extends('layouts.client')

@section('title', 'My Matters')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>My Matters</h2>
                <span>{{ $matters->total() }} matter(s)</span>
            </div>
        </div>

        <form class="kfms-table-toolbar" method="GET" action="{{ route('client.matters.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search matter title or reference">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Search</button>
                <a class="kfms-link-btn" href="{{ route('client.matters.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Matter</th>
                        <th>Practice Area</th>
                        <th>Status</th>
                        <th>Assigned Advocate</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($matters as $matter)
                        <tr>
                            <td>{{ $matter->reference_no }}</td>
                            <td>{{ $matter->title }}</td>
                            <td>{{ $matter->practiceArea?->name ?: '-' }}</td>
                            <td><span class="kfms-status is-active">{{ $matter->statusLabel() }}</span></td>
                            <td>{{ $matter->assignments->firstWhere('is_lead', true)?->user?->name ?: $matter->assignments->first()?->user?->name ?: '-' }}</td>
                            <td>
                                <div class="kfms-inline-actions">
                                    <a href="{{ route('client.matters.show', $matter) }}">Open</a>
                                    <a href="{{ route('client.matters.show', $matter) }}#messages">Message</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="kfms-empty">No matters found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $matters->links() }}
    </section>
@endsection
