# Authentication and Access Control Flow

Last updated: June 26, 2026

## Purpose

This document describes the current authentication, staff approval, and role/permission authorization flow for Kalikumutima FMS / JurisFlow.

The system uses Laravel Fortify, Jetstream, Sanctum, and Spatie Laravel Permission.

## Core Concepts

- **Authentication** confirms who the user is.
- **Email verification** confirms the account email is verified where the route requires it.
- **Staff status** controls whether the authenticated user can enter the workspace.
- **Roles** group permissions.
- **Permissions** control access to modules and administrative actions.
- **Requested role** is stored during registration but is not granted until approval.

## Main Files

- Registration action: `app/Actions/Fortify/CreateNewUser.php`
- Staff status middleware: `app/Http/Middleware/EnsureStaffIsActive.php`
- Middleware aliases: `bootstrap/app.php`
- Protected routes: `routes/web.php`
- Access control controller: `app/Http/Controllers/AccessControlController.php`
- Registration view: `resources/views/auth/register.blade.php`
- Pending access view: `resources/views/auth/access-pending.blade.php`
- Sidebar permission visibility: `resources/views/layouts/partials/sidebar.blade.php`
- Navbar quick-action visibility: `resources/views/layouts/partials/navbar.blade.php`
- Staff profile table: `database/migrations/2026_06_25_130030_create_staff_profiles_table.php`
- Requested role migration: `database/migrations/2026_06_26_180000_add_requested_role_to_staff_profiles_table.php`
- Roles and permissions seed data: `database/seeders/DatabaseSeeder.php`

## Registration Flow

1. A staff member opens the registration screen.
2. The registration form collects:
   - full name
   - email
   - phone
   - job title
   - branch
   - department
   - requested role
   - password
3. The requested role list excludes protected roles:
   - `Super Admin`
   - `Administrator`
4. `CreateNewUser` creates the user account.
5. `CreateNewUser` creates a related `StaffProfile` with:
   - `employment_status = pending`
   - `requested_role = selected role`
   - branch and department
6. The requested role is not assigned to the user at registration time.
7. After registration, the user is signed out and redirected to the login page with a message asking them to wait for administrator approval.

This means registration creates an access request, not an active permission grant.

## Login Flow

1. The user signs in through the Fortify/Jetstream login route.
2. Authenticated web routes require:
   - `auth:sanctum`
   - Jetstream auth session middleware
   - `verified`
3. Routes that enter the main workspace also require `active.staff`.
4. `active.staff` checks the authenticated user's staff profile status.
5. Users with `employment_status = active` may continue.
6. Users with `pending`, `inactive`, or `suspended` are redirected to `/access-pending`.

Users without a staff profile are currently treated as active for compatibility with existing seeded or legacy users.

## Pending Access Flow

Pending users are redirected to:

`/access-pending`

The pending screen tells the user their account is waiting for administrator approval and provides a sign-out action.

## Approval Flow

Access managers use the Access Control module to approve pending staff.

1. A user with `manage access control` opens Approval Requests.
2. Pending staff profiles are listed from `staff_profiles`.
3. The screen displays the requested role from `staff_profiles.requested_role`.
4. The approver opens the review modal before approval.
5. During review, the approver can confirm or correct:
   - phone
   - job title
   - branch
   - department
   - approved role
6. When the reviewed request is approved:
   - the user is assigned the approved role
   - `employment_status` is changed to `active`
   - branch and department are synchronized to both the user record and staff profile
   - phone and job title are saved on the staff profile
7. After approval, the user may enter modules allowed by their role permissions.

Blind approval from the table is intentionally not available. Pending users should be reviewed first so incorrect department, branch, contact, or role details can be corrected before access is granted.

## Status Values

Staff status is stored in `staff_profiles.employment_status`.

Supported values:

- `pending`: waiting for approval
- `active`: allowed through `active.staff`
- `inactive`: blocked from workspace routes
- `suspended`: blocked from workspace routes

## Authorization Model

The app uses Spatie permissions as route middleware.

| Area | Required Permission |
| --- | --- |
| Dashboard | `view dashboard` |
| Clients | `manage clients` |
| Matters | `manage matters` |
| Recoveries | `manage recoveries` |
| Land Titles | `manage land titles` |
| Finance | `manage finance` |
| Staff | `manage staff` |
| Access Control | `manage access control` |
| System Settings | `manage settings` |
| Company Settings | `manage settings` |

The sidebar and navbar quick actions use the same permissions, so users should not see links they cannot access.

## Access Control Administration

The Access Control module is protected by:

`permission:manage access control`

Users with this permission can:

- view all users
- view pending approval requests
- approve users
- edit user roles and status
- delete users, except their own account
- create, update, and delete roles
- create, update, and delete permissions

Protected behavior:

- A user cannot delete their own account.
- A user cannot remove their own access-control permission or deactivate their own account.
- `Super Admin` and `Administrator` roles cannot be deleted.
- `Super Admin` and `Administrator` roles cannot be renamed.
- Core system permissions cannot be deleted.

## Seeded Roles and Permissions

The default permissions are seeded in `DatabaseSeeder`.

Core permissions:

- `view dashboard`
- `manage clients`
- `manage matters`
- `manage litigation`
- `manage recoveries`
- `manage land titles`
- `manage finance`
- `manage staff`
- `manage access control`
- `approve requests`
- `manage settings`

The seeded `Super Admin` user receives:

- `Super Admin`
- `Administrator`

The seeded administrator receives:

- `Administrator`

Both seeded users are given active staff profiles.

## Route Protection Summary

All workspace routes sit behind this base stack:

```text
auth:sanctum
Jetstream auth session
verified
active.staff
permission:<module permission>
```

The pending access route sits behind authentication and verification, but not `active.staff`, so blocked users can still see their pending status page.

## Test Coverage

The flow is covered by:

- `tests/Feature/AccessControlFlowTest.php`
- `tests/Feature/RegistrationTest.php`
- settings route updates in:
  - `tests/Feature/CompanySettingTest.php`
  - `tests/Feature/SystemSettingTest.php`

Important assertions:

- pending users are redirected to the pending access screen
- module permissions are enforced
- approval activates a user and grants the requested role
- registration stores a requested role but does not grant it immediately

## Operational Notes

- Run migrations after deployment so `staff_profiles.requested_role` exists.
- Run seeders when setting up a fresh environment so the baseline roles and permissions exist.
- Keep module route permissions, sidebar visibility, and navbar quick actions aligned whenever a new module is added.
- Avoid granting roles during registration. Role assignment should happen through approval or explicit administrator action.
