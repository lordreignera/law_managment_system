<?php

namespace App\Http\Controllers;

use App\Models\Matter;
use App\Models\User;
use Illuminate\Http\Request;

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
        ]);

        $matter->update([
            'description' => $data['description'],
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
        ]);

        $matter->addAttachment($request->file('document'), [
            'category' => $data['category'] ?? 'matter-document',
        ]);

        return redirect()
            ->route('matters.instructions.show', $matter)
            ->with('status', 'Document uploaded.');
    }

    private function ensureVisible(Matter $matter, ?User $user): void
    {
        abort_unless($user && Matter::visibleTo($user)->whereKey($matter->getKey())->exists(), 404);
    }
}
