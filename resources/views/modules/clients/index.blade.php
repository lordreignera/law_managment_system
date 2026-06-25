@extends('layouts.admin')

@section('title', 'Clients')
@section('page-title', 'Clients')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <h2>Client Register</h2>
            <span>{{ $clients->total() }} records</span>
        </div>
        @include('modules.partials.table', [
            'headers' => ['Name', 'Type', 'Phone', 'Email', 'Status'],
            'rows' => $clients->map(fn ($client) => [$client->name, $client->client_type, $client->phone, $client->email, $client->status]),
        ])
        {{ $clients->links() }}
    </section>
@endsection
