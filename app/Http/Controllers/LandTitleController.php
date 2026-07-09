<?php

namespace App\Http\Controllers;

use App\Exports\LandTitlesExport;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\LandTitle;
use App\Models\Matter;
use App\Models\User;
use App\Models\ZonalOffice;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class LandTitleController extends Controller
{
    public function index(Request $request)
    {
        $titles = LandTitle::query()
            ->with(['bank', 'bankBranch', 'zonalOffice', 'handler'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('borrower_name', 'like', "%{$search}%")
                        ->orWhere('instruction_type', 'like', "%{$search}%")
                        ->orWhere('received_from', 'like', "%{$search}%")
                        ->orWhere('returned_to', 'like', "%{$search}%")
                        ->orWhereHas('bank', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('bankBranch', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('zonalOffice', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.land-titles.index', [
            'titles' => $titles,
            'filters' => $request->only(['search', 'status']),
            'statuses' => LandTitle::STATUSES,
        ]);
    }

    public function create()
    {
        return view('modules.land-titles.create', $this->formData([
            'title' => new LandTitle([
                'reference_no' => MonthlyReferenceNumber::make(LandTitle::class, 'reference_no', 'SEC'),
                'status' => 'pending',
                'received_at' => now(),
            ]),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validateSecurity($request);
        unset($data['documents']);
        $data['reference_no'] = MonthlyReferenceNumber::make(LandTitle::class, 'reference_no', 'SEC');

        $title = LandTitle::create($data);
        $this->storeDocuments($request, $title);

        return redirect()
            ->route('land-titles.show', $title)
            ->with('status', 'Security registered.');
    }

    public function show(LandTitle $landTitle)
    {
        return view('modules.land-titles.show', [
            'title' => $landTitle->load(['bank', 'bankBranch', 'zonalOffice', 'matter.client', 'handler', 'attachments.uploader']),
        ]);
    }

    public function edit(LandTitle $landTitle)
    {
        return view('modules.land-titles.edit', $this->formData([
            'title' => $landTitle,
        ]));
    }

    public function update(Request $request, LandTitle $landTitle)
    {
        $data = $this->validateSecurity($request);
        unset($data['documents']);

        $landTitle->update($data);
        $this->storeDocuments($request, $landTitle);

        return redirect()
            ->route('land-titles.show', $landTitle)
            ->with('status', 'Security updated.');
    }

    public function returnSecurity(Request $request, LandTitle $landTitle)
    {
        $data = $request->validate([
            'returned_to' => ['required', 'string', 'max:191'],
            'returned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $landTitle->update([
            'status' => 'returned',
            'returned_to' => $data['returned_to'],
            'returned_at' => $data['returned_at'],
            'notes' => $data['notes'] ?? $landTitle->notes,
        ]);

        $this->storeDocuments($request, $landTitle);

        return redirect()
            ->route('land-titles.show', $landTitle)
            ->with('status', 'Security marked as returned.');
    }

    public function destroy(LandTitle $landTitle)
    {
        $landTitle->delete();

        return redirect()
            ->route('land-titles.index')
            ->with('status', 'Security deleted.');
    }

    public function export(Request $request)
    {
        return Excel::download(
            new LandTitlesExport($request->only(['search', 'status'])),
            'securities-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'banks' => Bank::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'bankBranches' => BankBranch::with('bank')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'bank_id', 'name', 'office_location']),
            'zonalOffices' => ZonalOffice::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'office_location']),
            'matters' => Matter::with('client')->latest()->limit(200)->get(['id', 'reference_no', 'title', 'client_id']),
            'handlers' => User::orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => LandTitle::STATUSES,
        ], $overrides);
    }

    private function validateSecurity(Request $request): array
    {
        return $request->validate([
            'bank_id' => ['nullable', 'exists:banks,id'],
            'bank_branch_id' => ['nullable', 'exists:bank_branches,id'],
            'zonal_office_id' => ['nullable', 'exists:zonal_offices,id'],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'handled_by' => ['nullable', 'exists:users,id'],
            'borrower_name' => ['required', 'string', 'max:191'],
            'instruction_type' => ['nullable', 'string', 'max:191'],
            'instruction_date' => ['nullable', 'date'],
            'received_from' => ['nullable', 'string', 'max:191'],
            'returned_to' => ['nullable', 'string', 'max:191'],
            'received_at' => ['nullable', 'date'],
            'dispatched_at' => ['nullable', 'date'],
            'returned_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(LandTitle::STATUSES))],
            'notes' => ['nullable', 'string', 'max:3000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);
    }

    private function storeDocuments(Request $request, LandTitle $title): void
    {
        foreach ($request->file('documents', []) as $document) {
            $title->addAttachment($document, [
                'category' => 'security_document',
                'title' => 'Security document',
            ]);
        }
    }
}
