<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::query()
            ->withCount(['users', 'departments'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('modules.branches.index', [
            'branches' => $branches,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return view('modules.branches.create', [
            'code' => Branch::nextCompanyCode(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateBranch($request);

        Branch::create($data);

        return redirect()
            ->route('branches.index')
            ->with('status', 'Branch created.');
    }

    public function edit(Branch $branch)
    {
        return view('modules.branches.edit', [
            'branch' => $branch,
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $this->validateBranch($request, $branch);

        $branch->update($data);

        return redirect()
            ->route('branches.index')
            ->with('status', 'Branch updated.');
    }

    private function validateBranch(Request $request, ?Branch $branch = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('branches', 'name')->ignore($branch?->id)],
            'code' => ['required', 'string', 'max:30', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'city' => ['nullable', 'string', 'max:191'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
