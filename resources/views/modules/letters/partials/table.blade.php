<div class="kfms-table-wrap">
    <table class="kfms-table">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Subject</th>
                <th>Client / Matter</th>
                <th>Recipient</th>
                <th>Type</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($letters as $letter)
                <tr>
                    <td><strong>{{ $letter->reference_no }}</strong></td>
                    <td>{{ $letter->subject }}</td>
                    <td>
                        {{ $letter->client?->display_name ?: $letter->matter?->client?->display_name ?: '-' }}<br>
                        <span class="kfms-muted">{{ $letter->matter?->reference_no ?: '-' }}</span>
                    </td>
                    <td>{{ $letter->recipient_name }}</td>
                    <td>{{ $letter->typeLabel() }}</td>
                    <td><span class="kfms-status kfms-status-{{ $letter->status }}">{{ $letter->statusLabel() }}</span></td>
                    <td>{{ $letter->letter_date?->format('d M Y') ?: '-' }}</td>
                    <td><a class="kfms-link-btn" href="{{ route('letters.show', $letter) }}">View</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="kfms-empty">No letters found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
