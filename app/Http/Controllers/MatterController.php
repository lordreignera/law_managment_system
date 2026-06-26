<?php

namespace App\Http\Controllers;

use App\Models\BusinessIndustry;
use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterCategory;
use App\Models\PracticeArea;
use App\Models\Shelf;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MatterController extends Controller
{
    public function index(Request $request)
    {
        $matters = Matter::with(['client', 'practiceArea'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.matters.index', [
            'matters' => $matters,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return view('modules.matters.create', [
            'matterNumber' => $this->nextMatterNumber(),
            'clients' => Client::orderBy('name')->get(),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'reference_no' => ['required', 'string', 'max:80', 'unique:matters,reference_no'],
            'client_id' => ['required', 'exists:clients,id'],
            'ultimate_client_id' => ['nullable', 'exists:clients,id'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'business_industry_id' => ['nullable', 'exists:business_industries,id'],
            'matter_category_id' => ['nullable', 'exists:matter_categories,id'],
            'shelf_id' => ['nullable', 'exists:shelves,id'],
            'opened_on' => ['required', 'date'],
            'closed_on' => ['nullable', 'date', 'after_or_equal:opened_on'],
            'privacy_status' => ['required', Rule::in(['public', 'private'])],
            'opposite_counsel' => ['nullable', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:3000'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['exists:users,id'],
        ]);

        DB::transaction(function () use ($data) {
            $partnerIds = $data['partner_ids'];
            $associateIds = $data['associate_ids'] ?? [];

            $matter = Matter::create(collect($data)
                ->except(['partner_ids', 'associate_ids'])
                ->merge([
                    'opened_by' => auth()->id(),
                    'branch_id' => auth()->user()->branch_id,
                    'department_id' => auth()->user()->department_id,
                    'status' => empty($data['closed_on']) ? 'open' : 'closed',
                ])
                ->toArray());

            foreach ($partnerIds as $index => $userId) {
                $matter->assignments()->create([
                    'user_id' => $userId,
                    'assignment_role' => 'partner',
                    'assigned_on' => now()->toDateString(),
                    'is_lead' => $index === 0,
                ]);
            }

            foreach (array_diff($associateIds, $partnerIds) as $userId) {
                $matter->assignments()->create([
                    'user_id' => $userId,
                    'assignment_role' => 'associate',
                    'assigned_on' => now()->toDateString(),
                ]);
            }
        });

        return redirect()
            ->route('matters.index')
            ->with('status', 'Matter created.');
    }

    private function nextMatterNumber(): string
    {
        $prefix = now()->format('Y').'-MAT';
        $lastNumber = Matter::where('reference_no', 'like', "{$prefix}-%")
            ->pluck('reference_no')
            ->map(fn ($reference) => (int) str_replace("{$prefix}-", '', $reference))
            ->max() ?? 0;

        return $prefix.'-'.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}
