<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::query()
            ->withCount(['users', 'departments'])
            ->with(['departments' => fn ($query) => $query->withCount('users')->orderBy('sort_order')->orderBy('name')])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when(! $request->user()?->hasRole('Super Admin'), fn ($query) => $query->whereKey($request->user()?->branch_id ?? 0))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('modules.branches.index', [
            'branches' => $branches,
            'filters' => $request->only(['search']),
            'createCode' => Branch::nextCompanyCode(),
        ]);
    }

    public function create()
    {
        $this->ensureCanCreateBranch();

        return view('modules.branches.create', [
            'code' => Branch::nextCompanyCode(),
        ]);
    }

    public function show(Branch $branch)
    {
        $this->ensureCanManageBranch($branch);

        return view('modules.branches.show', [
            'branch' => $branch->loadCount(['users', 'departments'])->load(['departments' => fn ($query) => $query->withCount('users')->orderBy('sort_order')->orderBy('name')]),
            'staffCount' => $branch->users()->count(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureCanCreateBranch();
        $data = $this->validateBranch($request);

        Branch::create($data);

        return redirect()
            ->route('branches.index')
            ->with('status', 'Branch created.');
    }

    public function edit(Branch $branch)
    {
        $this->ensureCanManageBranch($branch);

        return view('modules.branches.edit', [
            'branch' => $branch,
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $this->ensureCanManageBranch($branch);
        $data = $this->validateBranch($request, $branch);

        $branch->update($data);

        return redirect()
            ->route('branches.index')
            ->with('status', 'Branch updated.');
    }

    public function destroy(Branch $branch)
    {
        $this->ensureCanManageBranch($branch);

        abort_if(
            $branch->users()->exists() || $branch->departments()->exists(),
            422,
            'Branches with staff or departments cannot be deleted. Remove those records first.'
        );

        $branch->delete();

        return redirect()
            ->route('branches.index')
            ->with('status', 'Branch deleted.');
    }

    public function createDepartment(Branch $branch)
    {
        $this->ensureCanManageBranch($branch);

        return view('modules.branches.departments.create', [
            'branch' => $branch,
            'department' => new Department(['is_active' => true]),
        ]);
    }

    public function storeDepartment(Request $request, Branch $branch)
    {
        $this->ensureCanManageBranch($branch);
        $data = $this->validateDepartment($request, $branch);

        $branch->departments()->create($data);

        return redirect()
            ->route('branches.show', $branch)
            ->with('status', 'Department added to '.$branch->name.'.');
    }

    public function showDepartment(Branch $branch, Department $department)
    {
        $this->ensureCanManageBranch($branch);
        $this->ensureDepartmentBelongsToBranch($branch, $department);

        return view('modules.branches.departments.show', [
            'branch' => $branch,
            'department' => $department->load(['users.roles', 'users.staffProfile']),
        ]);
    }

    public function editDepartment(Branch $branch, Department $department)
    {
        $this->ensureCanManageBranch($branch);
        $this->ensureDepartmentBelongsToBranch($branch, $department);

        return view('modules.branches.departments.edit', [
            'branch' => $branch,
            'department' => $department,
        ]);
    }

    public function updateDepartment(Request $request, Branch $branch, Department $department)
    {
        $this->ensureCanManageBranch($branch);
        $this->ensureDepartmentBelongsToBranch($branch, $department);

        $department->update($this->validateDepartment($request, $branch, $department));

        return redirect()
            ->route('branches.show', $branch)
            ->with('status', 'Department updated.');
    }

    public function destroyDepartment(Branch $branch, Department $department)
    {
        $this->ensureCanManageBranch($branch);
        $this->ensureDepartmentBelongsToBranch($branch, $department);

        abort_if(
            $department->users()->exists() || StaffProfile::where('department_id', $department->id)->exists(),
            422,
            'Departments assigned to staff cannot be deleted. Reassign the staff first.'
        );

        $department->delete();

        return redirect()
            ->route('branches.show', $branch)
            ->with('status', 'Department deleted.');
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

    private function validateDepartment(Request $request, Branch $branch, ?Department $department = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('departments', 'name')
                    ->where(fn ($query) => $query->where('branch_id', $branch->id))
                    ->ignore($department?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function ensureDepartmentBelongsToBranch(Branch $branch, Department $department): void
    {
        abort_unless((int) $department->branch_id === (int) $branch->id, 404);
    }

    private function ensureCanManageBranch(Branch $branch): void
    {
        $user = request()->user();

        abort_unless(
            $user?->hasRole('Super Admin') || ((int) $user?->branch_id > 0 && (int) $user->branch_id === (int) $branch->id),
            403,
            'You can only manage your own branch.'
        );
    }

    private function ensureCanCreateBranch(): void
    {
        abort_unless(
            request()->user()?->hasRole('Super Admin'),
            403,
            'Only a Super Admin can create branches.'
        );
    }
}
