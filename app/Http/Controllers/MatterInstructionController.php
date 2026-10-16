<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MatterInstructionController extends Controller
{
    public function show(Matter $matter)
    {
        $this->ensureVisible($matter, request()->user());

        return view('modules.matters.instructions', [
            'matter' => $matter->load(['client', 'practiceArea', 'attachments.uploader']),
        ]);
    }

    public function update(Request $request, Matter $matter)
    {
        $this->ensureVisible($matter, $request->user());

        $data = $request->validate([
            'description' => ['required', 'string', 'max:5000'],
            'client_summary' => ['nullable', 'string', 'max:3000'],
        ]);

        $matter->update([
            'description' => $data['description'],
            'client_summary' => $data['client_summary'] ?? null,
        ]);

        return redirect()
            ->route('matters.instructions.show', $matter)
            ->with('status', 'Instructions updated.');
    }

    public function storeDocument(Request $request, Matter $matter)
    {
        $this->ensureVisible($matter, $request->user());

        $data = $request->validate([
            'document' => ['required', 'file', 'max:10240'],
            'category' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:191'],
            'document_key' => ['nullable', 'string', 'max:191'],
            'replace_document_id' => ['nullable', 'integer', 'exists:attachments,id'],
            'is_client_visible' => ['nullable', 'boolean'],
        ]);

        $replacement = null;
        if (! empty($data['replace_document_id'])) {
            $replacement = $matter->attachments()->whereKey($data['replace_document_id'])->first();
            abort_unless($replacement, 422, 'Select a document belonging to this matter.');
        }

        $documentKey = $data['document_key']
            ?? $replacement?->document_key
            ?? Str::slug(pathinfo($request->file('document')->getClientOriginalName(), PATHINFO_FILENAME));
        $version = ((int) $matter->attachments()->where('document_key', $documentKey)->max('version_number')) + 1;
        $previousVersions = $matter->attachments()
            ->where('document_key', $documentKey)
            ->where('is_current', true)
            ->get();
        $previousVersions->each(fn ($attachment) => $attachment->update(['is_current' => false]));

        $attachment = $matter->addAttachment($request->file('document'), [
            'category' => $data['category'] ?? 'matter-document',
            'title' => $data['title'] ?? $request->file('document')->getClientOriginalName(),
            'document_key' => $documentKey,
            'version_number' => $version,
            'is_current' => true,
            'is_client_visible' => $request->boolean('is_client_visible'),
        ]);
        AuditLog::recordFor($attachment, 'uploaded', [], ['version_number' => $version, 'document_key' => $documentKey]);
        $previousVersions->each(fn ($previous) => AuditLog::recordFor($previous, 'superseded', ['is_current' => true], ['is_current' => false, 'replaced_by' => $attachment->id]));

        return redirect()
            ->route('matters.instructions.show', $matter)
            ->with('status', 'Document uploaded.');
    }

    private function ensureVisible(Matter $matter, ?User $user): void
    {
        abort_unless($user && Matter::visibleTo($user)->whereKey($matter->getKey())->exists(), 404);
    }
}
