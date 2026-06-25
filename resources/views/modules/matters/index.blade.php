@extends('layouts.admin')

@section('title', 'Matters')
@section('page-title', 'Matters')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <h2>Matter Register</h2>
            <span>{{ $matters->total() }} records</span>
        </div>
        @include('modules.partials.table', [
            'headers' => ['Reference', 'Title', 'Client', 'Practice Area', 'Status'],
            'rows' => $matters->map(fn ($matter) => [$matter->reference_no, $matter->title, $matter->client?->name, $matter->practiceArea?->name, $matter->status]),
        ])
        {{ $matters->links() }}
    </section>
@endsection
