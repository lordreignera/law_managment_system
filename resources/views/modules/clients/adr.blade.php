@extends('layouts.admin')

@section('title', 'Start ADR')
@section('page-title', 'Alternative Dispute Resolution (ADR)')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Record ADR (Alternative Dispute Resolution)</h2>
                <span>{{ $client->client_no }} - {{ $client->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Client
            </a>
        </div>

        @include('modules.clients.partials.adr-form', ['inModal' => false])
    </section>
@endsection
