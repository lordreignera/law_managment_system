@extends('layouts.admin')

@section('title', 'Open Matter')
@section('page-title', 'Open Matter')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Open Matter</h2>
                <span>{{ $client->client_no }} - {{ $client->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Client
            </a>
        </div>

        <p class="kfms-muted-text">Open another matter for {{ $client->display_name }} and deliberately select which existing files belong to it.</p>
        @include('modules.matters.partials.client-open-form', ['inModal' => false])
    </section>
@endsection
