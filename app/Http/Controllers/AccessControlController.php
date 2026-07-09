<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\StaffAccountApproved;
use App\Support\RoutePermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlController extends Controller
{
    public function __construct(private RoutePermissionRegistry $routeRegistry)
    {
    }

    public function users(Request $request)
    {
        $this->ensureCanManageAccessControl();

        $users = User::with(['branch', 'department', 'roles', 'permissions', 'staffProfile'])
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
            'permissionGroups' => $this->permissionGroups(),
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

    public function showApproval(StaffProfile $profile)
    {
        $this->ensureCanManageAccessControl();

        abort_unless($profile->employment_status === 'pending', 404);

        return view('modules.access-control.approval-show', [
            'profile' => $profile->load(['user.roles', 'branch', 'department']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::whereNotIn('name', ['Super Admin', 'Administrator'])->orderBy('name')->get(),
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

        $user->notify(new StaffAccountApproved($requestedRole));

        return redirect()
            ->route('access.approvals.index')
            ->with('status', $user->name.' approved.');
    }

    public function updateUser(Request $request, User $user)
    {
        $this->ensureCanManageAccessControl();

        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'direct_permissions' => ['nullable', 'array'],
            'direct_permissions.*' => ['exists:permissions,name'],
            'employment_status' => ['required', Rule::in(['pending', 'active', 'inactive', 'suspended'])],
        ]);

        $roles = $data['roles'] ?? [];
        $directPermissions = $data['direct_permissions'] ?? [];

        // Compute the effective permission set after the update so we can
        // protect the actor's own access-control access.
        $effectivePermissions = Permission::whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->pluck('name')
            ->merge($directPermissions)
            ->unique();

        $keepsAccessControl = $effectivePermissions->contains('access.users.index');

        abort_if(
            $user->is(auth()->user()) && (! $keepsAccessControl || $data['employment_status'] !== 'active'),
            422,
            'You cannot remove your own access-control permission or deactivate your account.'
        );

        $user->syncRoles($roles);
        $user->syncPermissions($directPermissions);
        $user->staffProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['employment_status' => $data['employment_status'], 'branch_id' => $user->branch_id, 'department_id' => $user->department_id]
        );

        return back()->with('status', $user->name.' updated.');
    }

    public function destroyUser(Request $request, User $user)
    {
        $this->ensureCanManageAccessControl();
        abort_if($user->is(auth()->user()), 422, 'You cannot delete your own account.');

        $user->delete();

        $route = $request->input('redirect_to') === 'approvals'
            ? 'access.approvals.index'
            : 'access.users.index';

        return redirect()->route($route)->with('status', 'User deleted.');
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
            'permissionGroups' => $this->permissionGroups(),
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
            ->paginate(50)
            ->withQueryString();

        $routeNames = $this->routeRegistry->routeNames();

        return view('modules.access-control.permissions', [
            'permissions' => $permissions,
            'routeBound' => $routeNames,
            'registry' => $this->routeRegistry,
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

        abort_if(
            $this->routeRegistry->isRouteBound($permission->name),
            422,
            'Route-bound permissions cannot be renamed. Rename the route instead.'
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('permissions', 'name')->ignore($permission->id)],
        ]);

        $permission->update(['name' => $data['name']]);

        return back()->with('status', 'Permission updated.');
    }

    public function destroyPermission(Permission $permission)
    {
        $this->ensureCanManageAccessControl();

        abort_if(
            $this->routeRegistry->isRouteBound($permission->name),
            422,
            'Route-bound permissions cannot be deleted. Remove the route or rerun kfms:sync-route-permissions --prune.'
        );

        $permission->delete();

        return back()->with('status', 'Permission deleted.');
    }

    private function ensureCanManageAccessControl(): void
    {
        abort_unless(auth()->user()?->can('access.users.index'), 403);
    }

    /**
     * Permissions grouped by module slug for the role/user edit pickers.
     * Returns ['Module Label' => Collection<Permission>] sorted by label.
     */
    private function permissionGroups(): array
    {
        $all = Permission::orderBy('name')->get();
        $registry = $this->routeRegistry;
        $groups = [];

        foreach ($all as $permission) {
            $slug = $registry->moduleSlug($permission->name);
            $label = $registry->moduleLabel($slug);
            $groups[$label] ??= collect();
            $groups[$label]->push($permission);
        }

        ksort($groups);

        return $groups;
    }
}
