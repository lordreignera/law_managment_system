<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\PublicHoliday;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\RoutePermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        CompanySetting::query()->firstOrCreate(['id' => 1], CompanySetting::defaults());

        $this->call([
            SalutationSeeder::class,
            CountrySeeder::class,
            ContactPositionSeeder::class,
            RelationshipTypeSeeder::class,
            PracticeAreaSeeder::class,
            BusinessIndustrySeeder::class,
            MatterCategorySeeder::class,
            ShelfSeeder::class,
            InstructionTypeSeeder::class,
            JobTitleSeeder::class,
            BudgetCategorySeeder::class,
            CourtSeeder::class,
            JudicialOfficerSeeder::class,
            OpposingCounselSeeder::class,
            BankSeeder::class,
            BankBranchSeeder::class,
            ZonalOfficeSeeder::class,
            CentenaryRegionSeeder::class,
            LeaveTypeSeeder::class,
            PaymentModeSeeder::class,
            BillingTypeSeeder::class,
            CurrencyTypeSeeder::class,
            TaxSettingSeeder::class,
            AccountClassSeeder::class,
            ChartAccountSeeder::class,
            FinanceAccountMappingSeeder::class,
            RequisitionCategorySeeder::class,
            ExpenseCategorySeeder::class,
            LetterheadSeeder::class,
            LetterTemplateSeeder::class,
            BillableRateSeeder::class,
            NotificationTemplateSeeder::class,
        ]);

        // ============================================================
        // Permissions & roles — route-name based.
        //
        // We wipe Spatie's permission / role tables and re-seed from the
        // current route table so the permission catalogue is always exactly
        // what the application can route to. Direct user grants and role
        // assignments are also cleared so the new baseline applies cleanly.
        // ============================================================
        DB::table('model_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('role_has_permissions')->delete();
        Permission::query()->delete();
        Role::query()->delete();

        Artisan::call('kfms:sync-route-permissions', ['--quiet-output' => true]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $registry = app(RoutePermissionRegistry::class);
        $allPermissions = $registry->routeNames()->all();

        $roleModuleMap = [
            // Super Admin / Administrator get every route. Super Admin is also
            // gate-bypassed in AppServiceProvider, but Administrator must have
            // each permission explicitly so it still works after a wipe.
            'Super Admin' => array_keys(RoutePermissionRegistry::MODULES),
            'Administrator' => array_keys(RoutePermissionRegistry::MODULES),

            'Managing Partner' => ['dashboard', 'messages', 'clients', 'intakes', 'matters', 'litigation', 'letters', 'recoveries', 'land-titles', 'finance', 'expenses', 'petty-cash', 'ledger', 'hr', 'staff', 'leave', 'requisitions', 'branches', 'holidays'],
            'Department Manager' => ['dashboard', 'messages', 'leave'],
            'Senior Partner' => ['dashboard', 'messages', 'clients', 'intakes', 'matters', 'litigation', 'letters'],
            'Litigation Officer' => ['dashboard', 'messages', 'clients', 'intakes', 'matters', 'litigation', 'letters', 'calendar'],
            'Advocate' => ['dashboard', 'messages', 'clients', 'intakes', 'matters', 'litigation', 'letters'],
            'Paralegal' => ['dashboard', 'messages', 'intakes', 'matters', 'litigation', 'letters'],
            'Recoveries Manager' => ['dashboard', 'messages', 'recoveries'],
            'Recovery Officer' => ['dashboard', 'messages'],
            'Securities Manager' => ['dashboard', 'messages', 'land-titles'],
            'Accountant' => ['dashboard', 'messages', 'finance', 'expenses', 'petty-cash', 'ledger', 'requisitions'],
            'HR Manager' => ['dashboard', 'messages', 'hr', 'staff', 'leave'],
            'Front Desk' => ['dashboard', 'messages', 'clients', 'intakes', 'letters'],
            'IT Manager' => ['dashboard', 'messages', 'access', 'settings', 'branches', 'holidays'],
        ];

        foreach ($roleModuleMap as $roleName => $modules) {
            $perms = in_array($roleName, ['Super Admin', 'Administrator'], true)
                ? $allPermissions
                : $registry->permissionsForModules($modules);
            Role::create(['name' => $roleName, 'guard_name' => 'web'])->syncPermissions($perms);
        }

        Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);

        $broadcastPermission = Permission::firstOrCreate([
            'name' => 'messages.broadcast',
            'guard_name' => 'web',
        ]);

        foreach (['Super Admin', 'Administrator', 'Managing Partner'] as $roleName) {
            Role::findByName($roleName)->givePermissionTo($broadcastPermission);
        }

        // Self-service leave (request + view own + cancel) for every staff role
        // that is not already a leave approver. Approval routes are withheld.
        $leaveSelfService = array_values(array_intersect(
            ['leave.index', 'leave.create', 'leave.store', 'leave.show', 'leave.cancel', 'leave.reschedule.edit', 'leave.reschedule.update'],
            $allPermissions
        ));

        foreach (['Senior Partner', 'Litigation Officer', 'Advocate', 'Paralegal', 'Recoveries Manager', 'Recovery Officer', 'Accountant', 'Front Desk'] as $roleName) {
            Role::findByName($roleName)->givePermissionTo($leaveSelfService);
        }

        // Self-service requisitions (raise + view own + cancel) for staff roles
        // that are not requisition approvers. Approval routes are withheld.
        $requisitionSelfService = array_values(array_intersect(
            ['requisitions.index', 'requisitions.create', 'requisitions.store', 'requisitions.show', 'requisitions.cancel'],
            $allPermissions
        ));

        foreach (['Senior Partner', 'Litigation Officer', 'Advocate', 'Paralegal', 'Recoveries Manager', 'Recovery Officer', 'HR Manager', 'Front Desk'] as $roleName) {
            Role::findByName($roleName)->givePermissionTo($requisitionSelfService);
        }

        // Firm Calendar is available to every staff role: anyone can view the
        // branch-scoped calendar and schedule meetings, court dates & reminders.
        $calendarPermissions = array_values(array_filter(
            $allPermissions,
            fn (string $permission) => str_starts_with($permission, 'calendar.')
        ));

        foreach ([
            'Managing Partner', 'Senior Partner', 'Litigation Officer', 'Advocate', 'Paralegal',
            'Recoveries Manager', 'Recovery Officer', 'Accountant',
            'HR Manager', 'Front Desk', 'IT Manager',
        ] as $roleName) {
            Role::findByName($roleName)->givePermissionTo($calendarPermissions);
        }

        // Recovery Officers get a limited slice of the Recoveries module: they
        // view their own assigned accounts and log demands/payments, but do not
        // register, edit, or reassign accounts (that is the manager's job).
        $recoveryOfficerSelfService = array_values(array_intersect(
            ['recoveries.mine', 'recoveries.mine.export', 'recoveries.show', 'recoveries.activities.store'],
            $allPermissions
        ));

        Role::findByName('Recovery Officer')->givePermissionTo($recoveryOfficerSelfService);

        $kampala = Branch::firstOrCreate(['name' => 'Kampala'], ['city' => 'Kampala']);

        // Uganda fixed-date public holidays (recur every year). Movable feasts
        // such as Easter and the two Eids are added manually by an administrator.
        foreach ([
            ['name' => "New Year's Day", 'month' => 1, 'day' => 1],
            ['name' => 'NRM Liberation Day', 'month' => 1, 'day' => 26],
            ['name' => 'Archbishop Janani Luwum Day', 'month' => 2, 'day' => 16],
            ['name' => "International Women's Day", 'month' => 3, 'day' => 8],
            ['name' => 'Labour Day', 'month' => 5, 'day' => 1],
            ['name' => 'Uganda Martyrs Day', 'month' => 6, 'day' => 3],
            ['name' => 'National Heroes Day', 'month' => 6, 'day' => 9],
            ['name' => 'Independence Day', 'month' => 10, 'day' => 9],
            ['name' => 'Christmas Day', 'month' => 12, 'day' => 25],
            ['name' => 'Boxing Day', 'month' => 12, 'day' => 26],
        ] as $holiday) {
            PublicHoliday::firstOrCreate(
                ['name' => $holiday['name']],
                [
                    'date' => now()->setMonth($holiday['month'])->setDay($holiday['day'])->startOfDay(),
                    'is_recurring' => true,
                ]
            );
        }

        foreach ([
            'Litigation',
            'Recoveries',
            'Securities and Land Titles',
            'Finance and Administration',
            'Human Resources',
        ] as $department) {
            Department::firstOrCreate(
                ['name' => $department],
                ['branch_id' => $kampala->id]
            );
        }

        $financeDepartmentId = Department::where('name', 'Finance and Administration')->value('id');

        $superAdmin = User::updateOrCreate(
            ['email' => 'info@kalikumutima.com'],
            [
                'name' => 'KFMS Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'branch_id' => $kampala->id,
                'department_id' => $financeDepartmentId,
            ]
        );

        $superAdmin->syncRoles(['Super Admin', 'Administrator']);
        StaffProfile::updateOrCreate(
            ['user_id' => $superAdmin->id],
            [
                'branch_id' => $kampala->id,
                'department_id' => $financeDepartmentId,
                'job_title' => 'System Administrator',
                'employment_status' => 'active',
            ]
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@kalikumutima.test'],
            [
                'name' => 'KFMS Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'branch_id' => $kampala->id,
                'department_id' => $financeDepartmentId,
            ]
        );

        $admin->syncRoles(['Administrator']);
        StaffProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'branch_id' => $kampala->id,
                'department_id' => $financeDepartmentId,
                'job_title' => 'Administrator',
                'employment_status' => 'active',
            ]
        );

        $welcomeConversation = Conversation::firstOrCreate(
            ['title' => 'Welcome to Kalikumutima Messages', 'created_by' => $superAdmin->id],
            [
                'audience_type' => 'users',
                'allow_replies' => true,
                'last_message_at' => now(),
            ]
        );

        foreach ([$superAdmin->id, $admin->id] as $userId) {
            $welcomeConversation->participants()->firstOrCreate(
                ['user_id' => $userId],
                ['last_read_at' => $userId === $superAdmin->id ? now() : null]
            );
        }

        $welcomeConversation->messages()->firstOrCreate(
            ['sender_id' => $superAdmin->id, 'body' => 'Internal messages are active. Use this space for personal notes, user messages, departmental notices, branch updates, and firm-wide communication.'],
            ['sent_at' => now()]
        );

    }
}
