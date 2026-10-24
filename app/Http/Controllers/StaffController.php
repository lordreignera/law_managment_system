<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\StaffAccountApproved;
use App\Support\ReferenceData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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

    public function create(Request $request)
    {
        $isAccessControlFlow = $request->routeIs('access.users.create');

        return view('modules.staff.create', [
            'branches' => Branch::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'managers' => $this->managerOptions(),
            'roles' => $this->assignableRoles(),
            'statuses' => $this->statuses(),
            'nextStaffNo' => StaffProfile::nextStaffNumber(),
            'formContext' => [
                'title' => $isAccessControlFlow ? 'Add User' : 'Register Staff',
                'heading' => $isAccessControlFlow ? 'Add User' : 'New Staff Member',
                'subtitle' => $isAccessControlFlow
                    ? 'Create approved login access and staff profile details'
                    : 'Create login access and HR profile details',
                'backUrl' => $isAccessControlFlow ? route('access.users.index') : route('staff.index'),
                'backLabel' => $isAccessControlFlow ? 'Back to Users' : 'Back to Staff',
                'action' => $isAccessControlFlow ? route('access.users.store') : route('staff.store'),
                'submitLabel' => $isAccessControlFlow ? 'Add User' : 'Register Staff',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'phone' => ['required', 'string', 'max:60'],
            'job_title' => ['required', 'string', 'max:191', ReferenceData::jobTitleRule()],
            'branch_id' => ['required', 'exists:branches,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'joined_on' => ['nullable', 'date'],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->whereNotIn('name', ['Super Admin', 'Administrator'])),
            ],
        ]);

        $this->validateManagerAssignment($data['manager_id'] ?? null, (int) $data['department_id']);

        $staff = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'branch_id' => $data['branch_id'],
            'department_id' => $data['department_id'],
            'manager_id' => $data['manager_id'] ?? null,
        ]);
        $staff->markEmailAsVerified();

        $staff->assignRole($data['role']);

        StaffProfile::create([
            'user_id' => $staff->id,
            'phone' => $data['phone'],
            'job_title' => $data['job_title'],
            'branch_id' => $data['branch_id'],
            'department_id' => $data['department_id'],
            'joined_on' => $data['joined_on'] ?? null,
            'employment_status' => 'active',
            'requested_role' => $data['role'],
        ]);

        // Never send a password over email. The administrator can provide the
        // initial credential through the firm's approved onboarding channel;
        // the user can then rotate it through the normal password flow.
        $staff->notify(new StaffAccountApproved($data['role'], null, true));

        $redirectRoute = $request->routeIs('access.users.store') ? 'access.users.index' : 'staff.show';

        return $request->routeIs('access.users.store')
            ? redirect()->route($redirectRoute)->with('status', $staff->name.' registered and approved.')
            : redirect()->route($redirectRoute, $staff)->with('status', $staff->name.' registered.');
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
            'managers' => $this->managerOptions($staff),
            'roles' => $this->assignableRoles(),
            'statuses' => $this->statuses(),
            'nextStaffNo' => $staff->staffProfile?->staff_no ?: StaffProfile::nextStaffNumber(),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        abort_if($staff->hasAnyRole(['Super Admin', 'Administrator']) && ! auth()->user()->hasRole('Super Admin'), 403, 'Protected administrator accounts can only be managed by a Super Admin.');

        $profile = $staff->staffProfile ?: new StaffProfile(['user_id' => $staff->id]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:60'],
            'job_title' => ['nullable', 'string', 'max:191', ReferenceData::jobTitleRule($profile->job_title)],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'joined_on' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in(array_keys($this->statuses()))],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'role' => [
                'nullable',
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->whereNotIn('name', ['Super Admin', 'Administrator'])),
            ],
        ]);

        abort_if(
            $staff->is(auth()->user()) && $data['employment_status'] !== 'active',
            422,
            'You cannot deactivate your own account from HR.'
        );

        $this->validateManagerAssignment($data['manager_id'] ?? null, $data['department_id'] ?? null, $staff);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
        ];

        if (filled($data['password'] ?? null)) {
            $userData['password'] = Hash::make($data['password']);
        }

        $staff->update($userData);

        if (filled($data['role'] ?? null)) {
            $staff->syncRoles([$data['role']]);
        }

        StaffProfile::updateOrCreate(
            ['user_id' => $staff->id],
            [
                'staff_no' => $profile->staff_no ?: StaffProfile::nextStaffNumber(),
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'joined_on' => $data['joined_on'] ?? null,
                'employment_status' => $data['employment_status'],
                'requested_role' => $data['role'] ?? $profile->requested_role,
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

    private function managerOptions(?User $exclude = null)
    {
        return User::query()
            ->with(['department', 'staffProfile'])
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude->id))
            ->where(function ($query) {
                $query->whereNull('account_type')->orWhere('account_type', '!=', 'client');
            })
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->where(function ($query) {
                $query
                    ->whereHas('roles.permissions', fn ($permission) => $permission->where('name', 'leave.approve'))
                    ->orWhereHas('permissions', fn ($permission) => $permission->where('name', 'leave.approve'));
            })
            ->orderBy('name')
            ->get();
    }

    private function validateManagerAssignment(?int $managerId, ?int $departmentId, ?User $staff = null): void
    {
        if (! $managerId) {
            return;
        }

        $manager = User::with('staffProfile')->findOrFail($managerId);

        abort_if($staff && $manager->is($staff), 422, 'A staff member cannot be their own supervisor.');
        abort_unless($manager->staffProfile?->employment_status === 'active', 422, 'The selected supervisor must be an active staff member.');
        abort_unless($manager->can('leave.approve'), 422, 'The selected supervisor must have leave approval permission.');

        $managerDepartmentId = $manager->department_id ?: $manager->staffProfile?->department_id;

        abort_if(
            $departmentId && $managerDepartmentId && (int) $departmentId !== (int) $managerDepartmentId,
            422,
            'The supervisor must belong to the same department.'
        );
    }
}
