<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = User::with(['branch', 'department', 'roles', 'staffProfile'])
            ->whereHas('staffProfile')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('staffProfile', fn ($query) => $query
                            ->where('staff_no', 'like', "%{$search}%")
                            ->orWhere('job_title', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('branch', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('department', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('roles', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->whereHas(
                'staffProfile',
                fn ($query) => $query->where('employment_status', $request->string('status')->toString())
            ))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.staff.index', [
            'staff' => $staff,
            'branches' => Branch::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'statuses' => $this->statuses(),
            'summary' => [
                'Total Staff' => StaffProfile::count(),
                'Active' => StaffProfile::where('employment_status', 'active')->count(),
                'Pending' => StaffProfile::where('employment_status', 'pending')->count(),
                'Inactive / Suspended' => StaffProfile::whereIn('employment_status', ['inactive', 'suspended'])->count(),
            ],
            'filters' => $request->only(['search', 'status', 'branch_id', 'department_id']),
        ]);
    }

    public function create()
    {
        return view('modules.staff.create', [
            'branches' => Branch::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'roles' => $this->assignableRoles(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'staff_no' => ['nullable', 'string', 'max:60', 'unique:staff_profiles,staff_no'],
            'phone' => ['required', 'string', 'max:60'],
            'job_title' => ['required', 'string', 'max:191'],
            'branch_id' => ['required', 'exists:branches,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'joined_on' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in(array_keys($this->statuses()))],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->whereNotIn('name', ['Super Admin', 'Administrator'])),
            ],
        ]);

        $staff = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'branch_id' => $data['branch_id'],
            'department_id' => $data['department_id'],
        ]);

        $staff->assignRole($data['role']);

        StaffProfile::create([
            'user_id' => $staff->id,
            'staff_no' => $data['staff_no'] ?? null,
            'phone' => $data['phone'],
            'job_title' => $data['job_title'],
            'branch_id' => $data['branch_id'],
            'department_id' => $data['department_id'],
            'joined_on' => $data['joined_on'] ?? null,
            'employment_status' => $data['employment_status'],
            'requested_role' => $data['role'],
        ]);

        return redirect()
            ->route('staff.show', $staff)
            ->with('status', $staff->name.' registered.');
    }

    public function show(User $staff)
    {
        $staff->load(['branch', 'department', 'roles', 'staffProfile']);

        abort_unless($staff->staffProfile, 404);

        return view('modules.staff.show', [
            'staff' => $staff,
        ]);
    }

    public function edit(User $staff)
    {
        $staff->load(['branch', 'department', 'roles', 'staffProfile']);

        abort_unless($staff->staffProfile, 404);

        return view('modules.staff.edit', [
            'staff' => $staff,
            'branches' => Branch::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $profile = $staff->staffProfile ?: new StaffProfile(['user_id' => $staff->id]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($staff->id)],
            'staff_no' => ['nullable', 'string', 'max:60', Rule::unique('staff_profiles', 'staff_no')->ignore($profile->id)],
            'phone' => ['nullable', 'string', 'max:60'],
            'job_title' => ['nullable', 'string', 'max:191'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'joined_on' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in(array_keys($this->statuses()))],
        ]);

        abort_if(
            $staff->is(auth()->user()) && $data['employment_status'] !== 'active',
            422,
            'You cannot deactivate your own account from HR.'
        );

        $staff->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
        ]);

        StaffProfile::updateOrCreate(
            ['user_id' => $staff->id],
            [
                'staff_no' => $data['staff_no'] ?? null,
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'joined_on' => $data['joined_on'] ?? null,
                'employment_status' => $data['employment_status'],
            ]
        );

        return redirect()
            ->route('staff.show', $staff)
            ->with('status', 'Staff profile updated.');
    }

    private function statuses(): array
    {
        return [
            'pending' => 'Pending',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended',
        ];
    }

    private function assignableRoles()
    {
        return Role::whereNotIn('name', ['Super Admin', 'Administrator'])
            ->orderBy('name')
            ->get();
    }
}
