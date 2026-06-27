<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlController extends Controller
{
    public function users(Request $request)
    {
        $this->ensureCanManageAccessControl();

        $users = User::with(['branch', 'department', 'roles', 'staffProfile'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('roles', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->whereHas('staffProfile', fn ($query) => $query->where('employment_status', $request->string('status')->toString())))
            ->when($request->filled('role'), fn ($query) => $query->role($request->string('role')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.access-control.users', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'filters' => $request->only(['search', 'status', 'role']),
        ]);
    }

    public function approvals(Request $request)
    {
        $this->ensureCanManageAccessControl();

        $profiles = StaffProfile::with(['user.roles', 'branch', 'department'])
            ->where('employment_status', 'pending')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('job_title', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.access-control.approvals', [
            'profiles' => $profiles,
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::whereNotIn('name', ['Super Admin', 'Administrator'])->orderBy('name')->get(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function approveUser(Request $request, User $user)
    {
        $this->ensureCanManageAccessControl();

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:60'],
            'job_title' => ['required', 'string', 'max:191'],
            'branch_id' => ['required', 'exists:branches,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'requested_role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->whereNotIn('name', ['Super Admin', 'Administrator'])),
            ],
        ]);

        $requestedRole = $data['requested_role'];

        $user->forceFill([
            'branch_id' => $data['branch_id'],
            'department_id' => $data['department_id'],
        ])->save();

        $user->syncRoles([$requestedRole]);

        $user->staffProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employment_status' => 'active',
                'requested_role' => $requestedRole,
                'branch_id' => $data['branch_id'],
                'department_id' => $data['department_id'],
                'job_title' => $data['job_title'],
                'phone' => $data['phone'],
            ]
        );

        return back()->with('status', $user->name.' approved.');
    }

    public function updateUser(Request $request, User $user)
    {
        $this->ensureCanManageAccessControl();

        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'employment_status' => ['required', Rule::in(['pending', 'active', 'inactive', 'suspended'])],
        ]);

        $roles = $data['roles'] ?? [];
        $keepsAccessControl = Role::with('permissions')
            ->whereIn('name', $roles)
            ->get()
            ->contains(fn (Role $role) => $role->permissions->contains('name', 'manage access control'));

        abort_if(
            $user->is(auth()->user()) && (! $keepsAccessControl || $data['employment_status'] !== 'active'),
            422,
            'You cannot remove your own access-control permission or deactivate your account.'
        );

        $user->syncRoles($roles);
        $user->staffProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['employment_status' => $data['employment_status'], 'branch_id' => $user->branch_id, 'department_id' => $user->department_id]
        );

        return back()->with('status', $user->name.' updated.');
    }

    public function destroyUser(User $user)
    {
        $this->ensureCanManageAccessControl();
        abort_if($user->is(auth()->user()), 422, 'You cannot delete your own account.');

        $user->delete();

        return back()->with('status', 'User deleted.');
    }

    public function roles(Request $request)
    {
        $this->ensureCanManageAccessControl();

        $roles = Role::with('permissions')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('modules.access-control.roles', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('name')->get(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function storeRole(Request $request)
    {
        $this->ensureCanManageAccessControl();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        Role::create(['name' => $data['name'], 'guard_name' => 'web'])
            ->syncPermissions($data['permissions'] ?? []);

        return back()->with('status', 'Role created.');
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->ensureCanManageAccessControl();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        abort_if(
            in_array($role->name, ['Super Admin', 'Administrator'], true) && $data['name'] !== $role->name,
            422,
            'Protected system roles cannot be renamed.'
        );

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('status', 'Role updated.');
    }

    public function destroyRole(Role $role)
    {
        $this->ensureCanManageAccessControl();
        abort_if(in_array($role->name, ['Super Admin', 'Administrator'], true), 422, 'Protected system role.');

        $role->delete();

        return back()->with('status', 'Role deleted.');
    }

    public function permissions(Request $request)
    {
        $this->ensureCanManageAccessControl();

        $permissions = Permission::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('modules.access-control.permissions', [
            'permissions' => $permissions,
            'filters' => $request->only(['search']),
        ]);
    }

    public function storePermission(Request $request)
    {
        $this->ensureCanManageAccessControl();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', 'unique:permissions,name'],
        ]);

        Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        return back()->with('status', 'Permission created.');
    }

    public function updatePermission(Request $request, Permission $permission)
    {
        $this->ensureCanManageAccessControl();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('permissions', 'name')->ignore($permission->id)],
        ]);

        $permission->update(['name' => $data['name']]);

        return back()->with('status', 'Permission updated.');
    }

    public function destroyPermission(Permission $permission)
    {
        $this->ensureCanManageAccessControl();

        abort_if(in_array($permission->name, $this->corePermissions(), true), 422, 'Protected system permission.');

        $permission->delete();

        return back()->with('status', 'Permission deleted.');
    }

    private function ensureCanManageAccessControl(): void
    {
        abort_unless(auth()->user()?->can('manage access control'), 403);
    }

    private function corePermissions(): array
    {
        return [
            'view dashboard',
            'manage clients',
            'manage intakes',
            'manage matters',
            'manage litigation',
            'manage recoveries',
            'manage land titles',
            'manage finance',
            'manage staff',
            'manage access control',
            'approve requests',
            'manage settings',
        ];
    }
}
