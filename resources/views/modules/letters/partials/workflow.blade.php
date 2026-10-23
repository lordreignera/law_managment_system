@php
    $letterSteps = [
        'draft' => ['label' => 'Draft', 'help' => 'Create content and attach supporting documents.'],
        'pending_review' => ['label' => 'Review', 'help' => 'Submit for partner or authorised review.'],
        'approved' => ['label' => 'Approved', 'help' => 'Ready to print, download, or send.'],
        'sent' => ['label' => 'Sent', 'help' => 'Record how and when it was sent.'],
        'received' => ['label' => 'Received Copy', 'help' => 'Upload the stamped or acknowledged copy.'],
        'closed' => ['label' => 'Closed', 'help' => 'Document cycle completed.'],
    ];

    $stepKeys = array_keys($letterSteps);
    $currentIndex = max(array_search($letter->status ?? 'draft', $stepKeys, true), 0);
@endphp

<div class="kfms-letter-steps" role="list" aria-label="Letter workflow steps">
    @foreach ($letterSteps as $status => $step)
        @php
            $index = array_search($status, $stepKeys, true);
            $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'next');
        @endphp
        <div class="kfms-letter-step is-{{ $state }}" role="listitem" title="{{ $step['help'] }}" aria-label="Step {{ $index + 1 }}: {{ $step['label'] }}. {{ $step['help'] }}">
            <span>{{ $index + 1 }}</span>
            <strong>{{ $step['label'] }}</strong>
        </div>
    @endforeach
</div>
