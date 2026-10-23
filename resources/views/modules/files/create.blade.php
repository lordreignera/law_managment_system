@extends('layouts.admin')

@section('title', 'Open File')
@section('page-title', 'Open File')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Open File</h2>
                <span>{{ $client->client_no }} - {{ $client->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Client
            </a>
        </div>

        @include('modules.files.partials.client-file-form', ['inModal' => false])
    </section>
@endsection
