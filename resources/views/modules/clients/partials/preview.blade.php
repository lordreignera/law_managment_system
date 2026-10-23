<div class="kfms-client-preview">
    <div class="kfms-client-preview-heading">
        <div>
            <span class="kfms-client-preview-kicker">{{ $client->client_no }} · {{ ucfirst($client->status) }}</span>
            <h3>{{ $client->display_name }}</h3>
        </div>
        <span class="kfms-client-preview-type">{{ ucfirst($client->client_type) }}{{ $client->is_prospect ? ' · Prospect' : '' }}</span>
    </div>

    <dl class="kfms-client-preview-list">
        <div><dt>Phone</dt><dd>{{ $client->phone ?: 'Not provided' }}</dd></div>
        <div><dt>Email</dt><dd>{{ $client->email ?: 'Not provided' }}</dd></div>
        <div><dt>Address</dt><dd>{{ $client->address ?: 'Not recorded' }}</dd></div>
        <div><dt>Client in charge</dt><dd>{{ $client->clientInCharge?->name ?: 'Not assigned' }}</dd></div>
    </dl>

    <div class="kfms-client-preview-records" aria-label="Linked work totals">
        <span><strong>{{ $client->matters_count }}</strong> Matters</span>
        <span><strong>{{ $client->files_count }}</strong> Files</span>
        <span><strong>{{ $client->adr_resolutions_count }}</strong> ADRs</span>
    </div>

    <details class="kfms-client-preview-more">
        <summary>Additional client information</summary>
        <dl class="kfms-client-preview-list kfms-client-preview-list-more">
            <div><dt>Organisation</dt><dd>{{ $client->organization_name ?: '-' }}</dd></div>
            <div><dt>Position</dt><dd>{{ $client->position?->name ?: '-' }}</dd></div>
            <div><dt>Country</dt><dd>{{ $client->country?->name ?: '-' }}</dd></div>
            <div><dt>NIN / Passport / Registration No.</dt><dd>{{ $client->nin_passport_no ?: '-' }}</dd></div>
            <div><dt>Date of birth</dt><dd>{{ $client->date_of_birth?->format('d M Y') ?: '-' }}</dd></div>
            <div><dt>Occupation</dt><dd>{{ $client->occupation ?: '-' }}</dd></div>
            <div><dt>TIN</dt><dd>{{ $client->tin ?: '-' }}</dd></div>
            @if ($client->nextOfKin)
                <div><dt>Next of kin</dt><dd>{{ $client->nextOfKin->display_name }}{{ $client->nextOfKin->phone ? ' · '.$client->nextOfKin->phone : '' }}</dd></div>
            @endif
        </dl>
    </details>

    <div class="kfms-client-preview-actions">
        <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}"><i class="mdi mdi-account-box-outline"></i> View full profile</a>
        <a class="kfms-link-btn" href="{{ route('clients.details.edit', $client) }}"><i class="mdi mdi-pencil"></i> Edit details</a>
        @if (auth()->user()->hasRole('Super Admin'))
            <button class="kfms-link-btn kfms-danger" type="button" data-client-delete-open data-delete-url="{{ route('clients.destroy', $client) }}" data-client-number="{{ $client->client_no }}" data-client-display-name="{{ $client->display_name }}">
                <i class="mdi mdi-delete-outline"></i> Delete client
            </button>
        @endif
    </div>
</div>
