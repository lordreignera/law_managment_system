# Access Control Flow

Last updated: June 27, 2026

## Purpose

This document describes how authorization works in Kalikumutima FMS.
For who-is-signed-in concerns (registration, approval, login, pending status), see [authentication-flow.md](authentication-flow.md).

The model is **route-per-permission**: every named workspace route is its own
Spatie permission. The route name *is* the permission key. Permissions are
granted to roles, to individual users, or both, all from the Access Control UI.

## Core Concepts

- **Permission** — a single Spatie record. Its name matches a named route
  (`clients.index`, `matters.engagement.update`) or, for custom business
  rules, a free-form key (`approve.invoices.over.500k`).
- **Role** — a named bundle of permissions. Users are assigned one or more
  roles.
- **Direct user grant** — a permission attached to a user directly, outside
  any role. Useful for one-off access without inventing a new role.
- **Super Admin** — a special role. Bypasses every permission check via
  `Gate::before` so a Super Admin can never be locked out of the system.
- **Route-bound vs custom** — permissions whose name matches a named route
  are managed by the sync command and locked from edit/delete in the UI.
  Custom permissions can be created, edited, and removed normally.

## Source-of-Truth Files

| Concern | File |
| --- | --- |
| Middleware enforcing per-route permission | [app/Http/Middleware/EnsureRoutePermission.php](../app/Http/Middleware/EnsureRoutePermission.php) |
| Middleware alias `route.permission` | [bootstrap/app.php](../bootstrap/app.php) |
| Route catalogue helper (registry) | [app/Support/RoutePermissionRegistry.php](../app/Support/RoutePermissionRegistry.php) |
| Sync command | [app/Console/Commands/SyncRoutePermissions.php](../app/Console/Commands/SyncRoutePermissions.php) |
| Super Admin bypass | [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php) |
| Auto-sync on dev (mtime watch) | [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php) |
| Protected workspace routes | [routes/web.php](../routes/web.php) |
| Role + permission baseline seeder | [database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php) |
| Access control UI controller | [app/Http/Controllers/AccessControlController.php](../app/Http/Controllers/AccessControlController.php) |
| Role / permission picker partials | [resources/views/modules/access-control/](../resources/views/modules/access-control) |
| Sidebar visibility checks | [resources/views/layouts/partials/sidebar.blade.php](../resources/views/layouts/partials/sidebar.blade.php) |
| Navbar visibility checks | [resources/views/layouts/partials/navbar.blade.php](../resources/views/layouts/partials/navbar.blade.php) |

## Request Lifecycle

```text
HTTP request
   │
   ├─ auth:sanctum          (must be signed in)
   ├─ jetstream auth session
   ├─ verified              (email verified)
   ├─ active.staff          (staff_profiles.employment_status = active)
   └─ route.permission      ──► EnsureRoutePermission
                                  ├─ Route::currentRouteName() = e.g. "matters.show"
                                  ├─ Gate::before fires
                                  │     └─ Super Admin role? → allow
                                  └─ $user->can($routeName)?
                                        ├─ permission exists, granted via role or direct → 200
                                        └─ otherwise                                       → 403
```

The pending-access screen (`/access-pending`) lives **outside** the workspace
group so a pending user can still see their status without holding any
permission.

## Permission Naming Convention

A permission name is **the route name verbatim**. Examples:

| Route | Permission |
| --- | --- |
| `Route::get('/dashboard', ...)->name('dashboard')` | `dashboard` |
| `Route::get('/clients', ...)->name('clients.index')` | `clients.index` |
| `Route::patch('/matters/{matter}/engagement', ...)->name('matters.engagement.update')` | `matters.engagement.update` |
| `Route::get('/access-control/roles', ...)->name('access.roles.index')` | `access.roles.index` |

The first dotted segment is the **module slug** used for grouping in the UI.
The module label is looked up in `RoutePermissionRegistry::MODULES`.

## Seeded Roles (Baseline)

Seeded by [DatabaseSeeder](../database/seeders/DatabaseSeeder.php) after the
permission catalogue is synced from the route table.

| Role | Modules granted |
| --- | --- |
| Super Admin | All (also bypassed by Gate::before) |
| Administrator | All (explicit so the bypass isn't required) |
| Managing Partner | dashboard, clients, intakes, matters, recoveries, land-titles, finance, staff |
| Senior Partner | dashboard, clients, intakes, matters |
| Advocate | dashboard, clients, intakes, matters |
| Paralegal | dashboard, intakes, matters |
| Recoveries Manager | dashboard, recoveries |
| Recovery Officer | dashboard, recoveries |
| Accountant | dashboard, finance |
| HR Manager | dashboard, staff |
| Front Desk | dashboard, clients, intakes |
| IT Manager | dashboard, access, settings |

Modules expand to *every* route permission in that module slug, so when a new
route is added the role automatically gains it on the next reseed.

## Direct User Permissions

A user can be granted permissions on top of their roles via
**Access Control → Users → Edit → Direct Permissions**. The picker is grouped
by module; ticking a row writes a row in Spatie's `model_has_permissions`
table. Direct permissions stack with role-derived permissions — Laravel's
`$user->can($ability)` returns true if either grants it.

The actor protection rule still applies: a user cannot remove their own
`access.users.index` permission or deactivate their own account.

## Adding a New Route

The expected flow for a developer adding a new route:

1. **Add the route** under the workspace group in
   [routes/web.php](../routes/web.php):

   ```php
   Route::middleware(['active.staff', 'route.permission'])->group(function () {
       Route::get('/matters/{matter}/tasks', [TaskController::class, 'index'])
           ->name('matters.tasks.index');
   });
   ```

2. **Trigger the sync.** In local development the sync runs automatically the
   next time the app is hit in the browser (an mtime watcher in
   `AppServiceProvider::booted` detects the changed `routes/web.php`). For CI
   or production, run:

   ```powershell
   php artisan kfms:sync-route-permissions
   ```

   This is also wired into `composer install`, `composer update`, and
   `composer create-project` via `composer.json`, so any fresh checkout picks
   up the right catalogue.

3. **Assign it** via the UI:
   - **Access Control → Roles → Edit `<role>`** to grant to a role.
   - **Access Control → Users → Edit `<user>` → Direct Permissions** to grant
     to a single user.

4. **(Optional) add a nav link.** In
   [sidebar.blade.php](../resources/views/layouts/partials/sidebar.blade.php)
   or [navbar.blade.php](../resources/views/layouts/partials/navbar.blade.php),
   use the route name as the permission key:

   ```php
   ['label' => 'Tasks', 'route' => 'matters.tasks.index',
    'permission' => 'matters.tasks.index'],
   ```

Until step 2 runs, the new route returns 403 for anyone who is not a Super
Admin. This is the intended safe-by-default behaviour.

## Auto-Sync Behaviour

`AppServiceProvider::autoSyncRoutePermissions` runs inside an
`$this->app->booted()` callback (so the routing provider has already loaded
the route table). It:

1. Bails out in `production` (deploy-time sync is the source of truth there).
2. Bails out from CLI to avoid recursion when other artisan commands boot.
3. Bails out if the `permissions` table doesn't exist yet (fresh checkout).
4. Compares `filemtime(routes/web.php)` against a marker file in
   `storage/framework/cache/kfms-routes-mtime`.
5. If they differ, calls `kfms:sync-route-permissions --quiet-output` and
   updates the marker.

The result: a developer adds a route, reloads any page once, and the
permission record now exists. No manual command needed in day-to-day work.

## Removing or Renaming a Route

- **Rename a route**: the next sync creates the new permission name; the old
  permission is left in place (intentionally — roles or users may still
  reference it). Re-tick the new permission on the appropriate roles/users,
  then run `php artisan kfms:sync-route-permissions --prune` to delete the
  orphaned entry.
- **Delete a route**: same as rename — the orphan stays until `--prune` is
  run. Removing it from roles is safe either way because the route no
  longer exists.
- **Pruning safety net**: the `--prune` option only deletes permissions
  whose name *looks* like a route name (starts with a known module slug or
  equals `dashboard`). Custom permissions like `approve.invoices.over.500k`
  are never pruned automatically.

## Custom (Non-Route) Permissions

For business rules that don't map to a single route — e.g. "may approve
expenses over a threshold", "may override conflict-check decisions" — create
a custom permission from **Access Control → Permissions → Add Permission**.
The naming is free-form. Custom permissions are marked `Source: Custom` in
the table and can be edited/deleted normally.

Application code reads them the usual way:

```php
if (auth()->user()->can('approve.invoices.over.500k')) {
    // ...
}
```

## UI Surfaces

| Page | What it does |
| --- | --- |
| Access Control → Users | List/search users, edit roles and direct permissions, approve pending requests, change employment status, delete users (except yourself) |
| Access Control → Approval Requests | Review pending staff registrations; the approver corrects branch/department/role before activating |
| Access Control → Roles | List/search roles, create new roles, attach permissions via grouped per-module picker, rename/delete (`Super Admin` and `Administrator` are locked) |
| Access Control → Permissions | List/search all permissions with module and source columns. Route-bound rows are read-only; custom rows are fully editable |

## Sidebar / Navbar Visibility

Both partials use the route-name permission as the `@can(...)` key, so menu
items and quick-action links appear if and only if the signed-in user has the
matching permission. Examples:

- Sidebar "Approved Clients" requires `clients.index`.
- Navbar quick-action "New Intake" requires `intakes.create`.
- Sidebar "Access Control" parent requires *any* of
  `access.users.index`, `access.approvals.index`, `access.roles.index`,
  `access.permissions.index` (via `permission_any` in the items array).

## Operational Notes

- Re-running the seeder (`php artisan db:seed`) **wipes** Spatie's
  permission/role tables and rebuilds them from the route catalogue plus the
  baseline role mapping. Any direct user grants are also cleared. Existing
  approved users keep their account but lose their role assignments, so
  re-approval (or re-ticking via the Users page) is required.
- The Super-Admin bypass is intentionally invisible from the UI; it cannot
  be unticked. To revoke it, remove the user from the `Super Admin` role
  entirely.
- The middleware uses Laravel's standard `$user->can()`, which respects
  Spatie's cache. UI edits to roles/permissions call `syncPermissions()` and
  invalidate the cache automatically. No queue worker restart is needed.

## Test Coverage

| Test | What it exercises |
| --- | --- |
| [AccessControlFlowTest::test_pending_staff_are_redirected_to_pending_access_screen](../tests/Feature/AccessControlFlowTest.php) | Pending users can't reach the workspace |
| [AccessControlFlowTest::test_module_permissions_are_enforced](../tests/Feature/AccessControlFlowTest.php) | A user with only `dashboard` can see the dashboard but is 403'd from `clients.index` |
| [AccessControlFlowTest::test_approval_activates_user_and_grants_requested_role](../tests/Feature/AccessControlFlowTest.php) | An approver with `access.users.approve` + related route permissions can activate a pending user |
| [ClientIntakeFlowTest](../tests/Feature/ClientIntakeFlowTest.php) | The intake → engagement → matter pipeline works with route-name permissions |
| [ClientManagementFlowTest](../tests/Feature/ClientManagementFlowTest.php) | Client CRUD + new engagement work with route-name permissions |
| [SystemSettingTest](../tests/Feature/SystemSettingTest.php) / [CompanySettingTest](../tests/Feature/CompanySettingTest.php) | Settings routes are guarded by per-route permissions |
