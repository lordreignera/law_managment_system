@extends('layouts.admin')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('content')
<section class="kfms-panel">
    <div class="kfms-panel-header"><div><h2>My Notifications</h2><span>Updates and reminders sent to you.</span></div></div>
    <div class="kfms-table-wrap"><table class="kfms-table">
        <thead><tr><th>Message</th><th>Received</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>@forelse ($notifications as $notification)
            <tr><td><strong>{{ $notification->data['subject'] ?? 'Notification' }}</strong><p>{{ $notification->data['body'] ?? '' }}</p></td>
                <td>{{ $notification->created_at->format('d M Y H:i') }}</td><td>{{ $notification->read_at ? 'Read' : 'Unread' }}</td>
                <td><div class="kfms-table-actions">
                    @if ($notification->data['url'] ?? null)<a class="kfms-link-btn" href="{{ $notification->data['url'] }}">View</a>@endif
                    @unless ($notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button type="submit" class="kfms-link-btn">Mark read</button></form>@endunless
                </div></td></tr>
        @empty <tr><td colspan="4" class="kfms-empty">You have no notifications yet.</td></tr> @endforelse</tbody>
    </table></div>
    {{ $notifications->links() }}
</section>
@endsection
