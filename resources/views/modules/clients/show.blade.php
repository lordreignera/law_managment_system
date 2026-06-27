@extends('layouts.admin')

@section('title', 'Client Details')
@section('page-title', 'Client Details')

@section('content')
    @php
        $nextOfKin = $client->nextOfKin;
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $client->display_name }}</h2>
                <span>{{ $client->client_no }} - {{ ucfirst($client->status) }}</span>
            </div>
            <div class="kfms-header-actions">
                <a class="kfms-link-btn" href="{{ route('clients.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back to Clients
                </a>
                <a class="kfms-link-btn" href="{{ route('clients.details.edit', $client) }}">
                    <i class="mdi mdi-pencil"></i>
                    Add More Details
                </a>
                <a class="kfms-btn" href="{{ route('clients.engagements.create', $client) }}">
                    <i class="mdi mdi-briefcase-plus"></i>
                    Add Engagement
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div><span>Client Type</span><strong>{{ ucfirst($client->client_type) }}</strong></div>
            <div><span>Prospect</span><strong>{{ $client->is_prospect ? 'Yes' : 'No' }}</strong></div>
            <div><span>Organisation Name</span><strong>{{ $client->organization_name ?: '-' }}</strong></div>
            <div><span>Salutation</span><strong>{{ $client->salutation?->name ?: '-' }}</strong></div>
            <div><span>First Name</span><strong>{{ $client->first_name ?: '-' }}</strong></div>
            <div><span>Middle Name</span><strong>{{ $client->middle_name ?: '-' }}</strong></div>
            <div><span>Last Name</span><strong>{{ $client->last_name ?: '-' }}</strong></div>
            <div><span>Gender</span><strong>{{ $client->gender ? ucfirst($client->gender) : '-' }}</strong></div>
            <div><span>Phone</span><strong>{{ $client->phone ?: '-' }}</strong></div>
            <div><span>Email</span><strong>{{ $client->email ?: '-' }}</strong></div>
            <div><span>Client In Charge</span><strong>{{ $client->clientInCharge?->name ?: '-' }}</strong></div>
            <div><span>Position</span><strong>{{ $client->position?->name ?: '-' }}</strong></div>
            <div><span>Country</span><strong>{{ $client->country?->name ?: '-' }}</strong></div>
            <div><span>NIN / Passport / Registration No</span><strong>{{ $client->nin_passport_no ?: '-' }}</strong></div>
            <div><span>Date of Birth</span><strong>{{ $client->date_of_birth?->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Occupation</span><strong>{{ $client->occupation ?: '-' }}</strong></div>
            <div><span>TIN</span><strong>{{ $client->tin ?: '-' }}</strong></div>
        </div>

        <div class="kfms-section-heading">
            <h3>Address</h3>
        </div>
        <p class="kfms-muted-text">{{ $client->address ?: 'No address recorded.' }}</p>

        <div class="kfms-section-heading">
            <h3>Next of Kin</h3>
        </div>
        @if ($nextOfKin)
            <div class="kfms-detail-grid">
                <div><span>Name</span><strong>{{ $nextOfKin->display_name }}</strong></div>
                <div><span>Relationship</span><strong>{{ $nextOfKin->relationshipType?->name ?: '-' }}</strong></div>
                <div><span>Salutation</span><strong>{{ $nextOfKin->salutation?->name ?: '-' }}</strong></div>
                <div><span>Gender</span><strong>{{ $nextOfKin->gender ? ucfirst($nextOfKin->gender) : '-' }}</strong></div>
                <div><span>Phone</span><strong>{{ $nextOfKin->phone ?: '-' }}</strong></div>
                <div><span>Email</span><strong>{{ $nextOfKin->email ?: '-' }}</strong></div>
                <div><span>NIN / Passport No</span><strong>{{ $nextOfKin->nin_passport_no ?: '-' }}</strong></div>
                <div><span>Date of Birth</span><strong>{{ $nextOfKin->date_of_birth?->format('d M Y') ?: '-' }}</strong></div>
                <div><span>Country</span><strong>{{ $nextOfKin->country?->name ?: '-' }}</strong></div>
            </div>
            <p class="kfms-muted-text">{{ $nextOfKin->address ?: 'No next-of-kin address recorded.' }}</p>
        @else
            <p class="kfms-muted-text">No next of kin recorded.</p>
        @endif

        <div class="kfms-section-heading">
            <h3>Engagements</h3>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Engagement No</th>
                        <th>Matter No</th>
                        <th>Title</th>
                        <th>Practice Area</th>
                        <th>Engagement</th>
                        <th>Matter Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($client->matters as $matter)
                        <tr>
                            <td>{{ $matter->engagement?->engagement_no ?: '-' }}</td>
                            <td>{{ $matter->reference_no }}</td>
                            <td>{{ $matter->title }}</td>
                            <td>{{ $matter->practiceArea?->name ?: '-' }}</td>
                            <td>{{ str($matter->engagement?->status ?? 'pending')->headline() }}</td>
                            <td>{{ $matter->statusLabel() }}</td>
                            <td><a class="kfms-link-btn" href="{{ route('matters.show', $matter) }}">Review</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No engagements recorded for this client.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
