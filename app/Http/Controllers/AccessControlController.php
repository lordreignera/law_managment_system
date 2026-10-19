<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\StaffAccountApproved;
use App\Support\ReferenceData;
use App\Support\RoutePermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlController extends Controller
{
    public function __construct(private RoutePermissionRegistry $routeRegistry) {}

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
            'filters' => $request->only(['search', 'status', 'role']),
        ]);
    }

    public function editUser(User $user)
    {
        $this->ensureCanManageAccessControl();

        return view('modules.access-control.user-edit', [
            'user' => $user->load(['branch', 'department', 'roles', 'permissions', 'staffProfile']),
            'managers' => User::query()
                ->with('department')
                ->whereKeyNot($user->id)
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
                ->get(),
            'roles' => Role::orderBy('name')->get(),
            'permissionGroups' => $this->permissionGroups(),
            'statuses' => ['pending', 'active', 'inactive', 'suspended'],
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

        $data = $request->validate($this->approvalRules($user->staffProfile));
        $result = $this->approveSelectedUsers([$user->id], [$user->id => $data]);

        return redirect()
            ->route('access.approvals.index')
            ->with('status', $user->name.' approved.')
            ->with('approval_notice_failures', $result['notice_failures']);
    }

    public function bulkApproveUsers(Request $request)
    {
        $this->ensureCanManageAccessControl();
        abort_unless($request->user()->can('access.users.approve'), 403);
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:191'],
        ]);
        $result = $this->approveSelectedUsers($data['user_ids']);
        $count = $result['approved'];
        $status = $count.' '.($count === 1 ? 'user' : 'users').' approved.';
        if ($result['skipped'] > 0) {
            $status .= ' '.$result['skipped'].' already approved; no duplicate approvals were made.';
        }

        return redirect()->route('access.approvals.index', array_filter(['search' => $data['search'] ?? null]))
            ->with('status', $status)
            ->with('approval_notice_failures', $result['notice_failures']);
    }

    private function approvalRules(?StaffProfile $profile): array
    {
        return [
            'phone' => ['required', 'string', 'max:60'],
            'job_title' => ['required', 'string', 'max:191', ReferenceData::jobTitleRule($profile?->job_title)],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'requested_role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->whereNotIn('name', ['Super Admin', 'Administrator'])),
            ],
        ];
    }

    private function approveSelectedUsers(array $userIds, array $overrides = []): array
    {
        $approved = [];
        $skipped = 0;
        DB::transaction(function () use ($userIds, $overrides, &$approved, &$skipped) {
            $users = User::whereIn('id', $userIds)->orderBy('id')->lockForUpdate()->get();
            $profiles = StaffProfile::whereIn('user_id', $userIds)->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id');
            if ($users->count() !== count($userIds)) {
                throw ValidationException::withMessages(['user_ids' => 'A selected request is no longer available. Refresh the list and try again.']);
            }
            foreach ($users as $user) {
                $profile = $profiles->get($user->id);
                if (! $profile || $user->account_type === 'client' || $user->hasAnyRole(['Super Admin', 'Administrator'])) {
                    throw ValidationException::withMessages(['user_ids' => $user->name.' is not a pending staff access request.']);
                }
                if ($profile->employment_status === 'active') {
                    $skipped++;
                    continue;
                }
                if ($profile->employment_status !== 'pending') {
                    throw ValidationException::withMessages(['user_ids' => $user->name.' is no longer pending approval.']);
                }
                $data = $overrides[$user->id] ?? $profile->only(['phone', 'job_title', 'branch_id', 'department_id', 'requested_role']);
                $validator = Validator::make($data, $this->approvalRules($profile));
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['user_ids' => $user->name.' needs review: '.$validator->errors()->first()]);
                }
                $department = Department::find($data['department_id']);
                if ($department->branch_id && (int) $department->branch_id !== (int) $data['branch_id']) {
                    throw ValidationException::withMessages(['user_ids' => $user->name.' needs review: the department must belong to the selected branch.']);
                }
                $user->forceFill(['branch_id' => $data['branch_id'], 'department_id' => $data['department_id']])->save();
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                $user->syncRoles([$data['requested_role']]);
                $profile->fill($data + ['employment_status' => 'active'])->save();
                AuditLog::recordFor($user, 'access_approved', ['employment_status' => 'pending'], $data + ['employment_status' => 'active']);
                $approved[] = [$user, $data['requested_role']];
            }
        });

        $noticeFailures = 0;
        foreach ($approved as [$user, $role]) {
            try {
                $user->notify(new StaffAccountApproved($role));
            } catch (\Throwable $exception) {
                $noticeFailures++;
                report($exception);
            }
        }

        return ['approved' => count($approved), 'skipped' => $skipped, 'notice_failures' => $noticeFailures];
    }

    public function updateUser(Request $request, User $user)
    {
        $this->ensureCanManageAccessControl();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'direct_permissions' => ['nullable', 'array'],
            'direct_permissions.*' => ['exists:permissions,name'],
            'employment_status' => ['required', Rule::in(['pending', 'active', 'inactive', 'suspended'])],
            'manager_id' => ['nullable', 'exists:users,id'],
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

        if (! empty($data['manager_id'])) {
            abort_if((int) $data['manager_id'] === (int) $user->id, 422, 'A user cannot be their own supervisor.');

            $manager = User::with('staffProfile')->findOrFail($data['manager_id']);
            abort_unless($manager->staffProfile?->employment_status === 'active', 422, 'The selected supervisor must be active.');
            abort_unless($manager->can('leave.approve'), 422, 'The selected supervisor must have leave approval permission.');
            $managerDepartmentId = $manager->department_id ?: $manager->staffProfile?->department_id;
            $userDepartmentId = $user->department_id ?: $user->staffProfile?->department_id;
            abort_if($managerDepartmentId && $userDepartmentId && (int) $managerDepartmentId !== (int) $userDepartmentId, 422, 'The supervisor must belong to the same department.');
        }

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'manager_id' => $data['manager_id'] ?? null,
        ];

        if (filled($data['password'] ?? null)) {
            $userData['password'] = Hash::make($data['password']);
        }

        $user->update($userData);
        $user->syncRoles($roles);
        $user->syncPermissions($directPermissions);
        $user->staffProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['employment_status' => $data['employment_status'], 'branch_id' => $user->branch_id, 'department_id' => $user->department_id]
        );

        return redirect()
            ->route('access.users.edit', $user)
            ->with('status', $user->name.' updated.');
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
