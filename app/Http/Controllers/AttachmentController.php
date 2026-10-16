<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\CourtEvent;
use App\Models\LeaveRequest;
use App\Models\Matter;
use App\Models\Message;
use App\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(Request $request, Attachment $attachment)
    {
        $this->authorizeAttachment($request, $attachment);
        $disk = Storage::disk($attachment->disk ?: config('filesystems.default'));

        abort_unless($disk->exists($attachment->path), 404);

        AuditLog::recordFor($attachment, 'downloaded', [], ['action' => 'download']);

        return $disk->download($attachment->path, $attachment->original_name);
    }

    public function view(Request $request, Attachment $attachment)
    {
        $this->authorizeAttachment($request, $attachment);
        $disk = Storage::disk($attachment->disk ?: config('filesystems.default'));

        abort_unless($disk->exists($attachment->path), 404);

        AuditLog::recordFor($attachment, 'viewed', [], ['action' => 'view']);

        $mime = strtolower((string) ($attachment->mime_type ?: 'application/octet-stream'));
        $safeInline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'text/plain'], true);

        return response($disk->get($attachment->path), 200, [
            'Content-Type' => $safeInline ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($safeInline ? 'inline' : 'attachment').'; filename="'.addslashes($attachment->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeAttachment(Request $request, Attachment $attachment): void
    {
        $user = $request->user();
        $parent = $attachment->attachable;

        if ($parent instanceof Message) {
            abort_unless($parent->conversation?->participants()->whereKey($user->id)->exists(), 403);

            return;
        }

        if ($parent instanceof Matter) {
            abort_unless(Matter::visibleTo($user)->whereKey($parent->id)->exists(), 403);

            return;
        }

        if ($parent instanceof CourtEvent) {
            abort_unless(Matter::visibleTo($user)->whereKey($parent->matter_id)->exists(), 403);

            return;
        }

        if ($parent instanceof Requisition) {
            abort_unless($parent->requested_by === $user->id || $user->can('requisitions.approve'), 403);

            return;
        }

        if ($parent instanceof LeaveRequest) {
            abort_unless($parent->user_id === $user->id || $user->can('leave.approve'), 403);

            return;
        }

        abort_unless($attachment->uploaded_by === $user->id || $user->can('attachments.view'), 403);
    }
}
