@extends('layouts.admin')

@section('title', 'Land Titles')
@section('page-title', 'Land Titles')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <h2>Land Title Registry</h2>
            <span>{{ $titles->total() }} records</span>
        </div>
        @include('modules.partials.table', [
            'headers' => ['Reference', 'Borrower', 'Instruction', 'Received', 'Returned', 'Status'],
            'rows' => $titles->map(fn ($title) => [$title->reference_no, $title->borrower_name, $title->instruction_type, $title->received_on?->format('d M Y'), $title->returned_on?->format('d M Y'), $title->status]),
        ])
        {{ $titles->links() }}
    </section>
@endsection
