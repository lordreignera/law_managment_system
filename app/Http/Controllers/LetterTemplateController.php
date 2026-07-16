<?php

namespace App\Http\Controllers;

use App\Models\LetterTemplate;
use App\Models\Letterhead;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LetterTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = LetterTemplate::query()
            ->with('letterhead')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('modules.letters.templates.index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'category']),
            'categories' => LetterTemplate::CATEGORIES,
            'letterheads' => Letterhead::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        LetterTemplate::create($this->validatedData($request) + [
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('letters.templates.index')
            ->with('status', 'Template created.');
    }

    public function update(Request $request, LetterTemplate $template)
    {
        $template->update($this->validatedData($request, $template));

        return redirect()
            ->route('letters.templates.index')
            ->with('status', 'Template updated.');
    }

    public function destroy(LetterTemplate $template)
    {
        if ($template->letters()->exists()) {
            return back()->withErrors(['template' => 'This template has letters linked to it. Deactivate it instead.']);
        }

        $template->delete();

        return redirect()
            ->route('letters.templates.index')
            ->with('status', 'Template deleted.');
    }

    private function validatedData(Request $request, ?LetterTemplate $template = null): array
    {
        $data = $request->validate([
            'letterhead_id' => ['nullable', 'exists:letterheads,id'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:40', Rule::unique('letter_templates', 'code')->ignore($template?->id)],
            'category' => ['required', Rule::in(array_keys(LetterTemplate::CATEGORIES))],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
