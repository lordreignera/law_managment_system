@extends('layouts.client')

@section('title', 'Client Dashboard')

@section('content')
    <section class="kfms-client-hero">
        <div>
            <span>Client workspace</span>
            <h1>Welcome, {{ $client->display_name }}</h1>
            <p>View your matters, shared documents, and communication with the advocates assigned to your work.</p>
        </div>
        <div class="kfms-client-hero-actions">
            <a href="{{ route('client.matters.index') }}"><i class="mdi mdi-briefcase-search-outline"></i> View My Matters</a>
            <a href="{{ route('client.messages.index') }}"><i class="mdi mdi-message-text-outline"></i> Messages</a>
        </div>
    </section>

    <div class="kfms-stat-grid kfms-client-kpis">
        <section class="kfms-card"><span class="kfms-card-label">Active Matters</span><strong class="kfms-stat">{{ number_format($summary['active_matters']) }}</strong></section>
        <section class="kfms-card"><span class="kfms-card-label">Shared Documents</span><strong class="kfms-stat">{{ number_format($summary['shared_documents']) }}</strong></section>
        <section class="kfms-card"><span class="kfms-card-label">Unread Messages</span><strong class="kfms-stat">{{ number_format($summary['unread_messages']) }}</strong></section>
        <section class="kfms-card"><span class="kfms-card-label">Assigned Advocates</span><strong class="kfms-stat">{{ number_format($summary['assigned_advocates']) }}</strong></section>
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>My Matters</h2>
                    <span>Recent matters connected to your client record</span>
                </div>
            </div>
            <div class="kfms-client-list">
                @forelse ($matters as $matter)
                    <a href="{{ route('client.matters.show', $matter) }}">
                        <strong>{{ $matter->title }}</strong>
                        <span>{{ $matter->reference_no }} · {{ $matter->statusLabel() }}</span>
                    </a>
                @empty
                    <div class="kfms-empty-state">No matters are currently linked to your portal.</div>
                @endforelse
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Messages</h2>
                    <span>Matter conversations with assigned advocates</span>
                </div>
            </div>
            <div class="kfms-client-list">
                @forelse ($conversations as $conversation)
                    <a href="{{ route('client.messages.show', $conversation) }}">
                        <strong>{{ $conversation->matter?->title ?: $conversation->title }}</strong>
                        <span>{{ $conversation->latestMessage?->body ?: 'No messages yet.' }}</span>
                    </a>
                @empty
                    <div class="kfms-empty-state">No matter messages yet.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
