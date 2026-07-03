<?php

namespace App\Http\Controllers;

use App\Models\BusinessIndustry;
use App\Models\Client;
use App\Models\File;
use App\Models\Matter;
use App\Models\MatterCategory;
use App\Models\PracticeArea;
use App\Models\Shelf;
use App\Models\User;
use App\Exports\MattersExport;
use App\Imports\MattersImport;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class MatterController extends Controller
{
    public function index(Request $request)
    {
        $matters = Matter::with(['client', 'practiceArea'])
            ->forBranchOf($request->user())
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
            'statuses' => Matter::STATUSES,
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(new MattersExport($request->user()), 'matters-'.now()->format('Ymd-His').'.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new MattersImport($request->user());
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('matters.index')
            ->with('status', "Imported {$import->imported} matter(s); skipped {$import->skipped}.");
    }

    public function show(Matter $matter)
    {
        return view('modules.matters.show', [
            'matter' => $matter->load(['client', 'practiceArea', 'files.billingType', 'files.attachments', 'assignments.user', 'courtEvents.court', 'courtEvents.assignee']),
        ]);
    }

    public function create()
    {
        return view('modules.matters.create', [
            'matterNumber' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
            'clients' => Client::orderBy('name')->get(),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'statuses' => Matter::STATUSES,
        ]);
    }

    public function createForClient(Client $client)
    {
        if ($client->matter) {
            return redirect()
                ->route('matters.show', $client->matter)
                ->with('status', 'This client already has a matter.');
        }

        abort_if($client->files()->doesntExist(), 422, 'Open a file for this client before opening a matter.');

        return view('modules.matters.open', [
            'client' => $client,
            'matterNumber' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
            'practiceAreas' => PracticeArea::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'businessIndustries' => BusinessIndustry::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'matterCategories' => MatterCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shelves' => Shelf::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'suggestedTitle' => $client->files()->latest()->value('file_name'),
        ]);
    }

    public function storeForClient(Request $request, Client $client)
    {
        abort_if((bool) $client->matter, 422, 'This client already has a matter.');
        abort_if($client->files()->doesntExist(), 422, 'Open a file for this client before opening a matter.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'business_industry_id' => ['nullable', 'exists:business_industries,id'],
            'matter_category_id' => ['nullable', 'exists:matter_categories,id'],
            'shelf_id' => ['nullable', 'exists:shelves,id'],
            'opened_on' => ['required', 'date'],
            'privacy_status' => ['required', Rule::in(['public', 'private'])],
            'opposite_counsel' => ['nullable', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:3000'],
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['exists:users,id'],
            'associate_ids' => ['nullable', 'array'],
            'associate_ids.*' => ['exists:users,id'],
        ]);

        $matter = DB::transaction(function () use ($client, $data) {
            $partnerIds = $data['partner_ids'];
            $associateIds = $data['associate_ids'] ?? [];

            $matter = Matter::create(collect($data)
                ->except(['partner_ids', 'associate_ids'])
                ->merge([
                    'client_id' => $client->id,
                    'opened_by' => auth()->id(),
                    'branch_id' => auth()->user()->branch_id,
                    'department_id' => auth()->user()->department_id,
                    'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
                    'status' => 'open',
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

            $client->files()->whereNull('matter_id')->update(['matter_id' => $matter->id]);

            return $matter;
        });

        return redirect()
            ->route('matters.show', $matter)
            ->with('status', 'Matter '.$matter->reference_no.' opened. All files for this client are now under it.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'client_id' => [
                'required',
                'exists:clients,id',
                function ($attribute, $value, $fail) {
                    if (Matter::where('client_id', $value)->exists()) {
                        $fail('This client already has a matter. Add files to the existing matter instead.');
                    }
                },
            ],
            'ultimate_client_id' => ['nullable', 'exists:clients,id'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'business_industry_id' => ['nullable', 'exists:business_industries,id'],
            'matter_category_id' => ['nullable', 'exists:matter_categories,id'],
            'shelf_id' => ['nullable', 'exists:shelves,id'],
            'opened_on' => ['required', 'date'],
            'closed_on' => ['nullable', 'date', 'after_or_equal:opened_on'],
            'status' => ['required', Rule::in(array_keys(Matter::STATUSES))],
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
                    'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
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

}
