<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\CompanySetting;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\File;
use App\Models\Matter;
use App\Models\PracticeArea;
use App\Models\PublicHoliday;
use App\Models\RecoveryClient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\MonthlyReferenceNumber;
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
            BankSeeder::class,
            BankBranchSeeder::class,
            ZonalOfficeSeeder::class,
            LeaveTypeSeeder::class,
            PaymentModeSeeder::class,
            BillingTypeSeeder::class,
            CurrencyTypeSeeder::class,
            RequisitionCategorySeeder::class,
            ExpenseCategorySeeder::class,
            LetterheadSeeder::class,
            BillableRateSeeder::class,
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

            'Managing Partner' => ['dashboard', 'messages', 'clients', 'intakes', 'matters', 'litigation', 'recoveries', 'land-titles', 'finance', 'expenses', 'petty-cash', 'ledger', 'hr', 'staff', 'leave', 'requisitions', 'branches', 'holidays'],
            'Senior Partner' => ['dashboard', 'messages', 'clients', 'intakes', 'matters', 'litigation'],
            'Litigation Officer' => ['dashboard', 'messages', 'clients', 'intakes', 'matters', 'litigation', 'calendar'],
            'Advocate' => ['dashboard', 'messages', 'clients', 'intakes', 'matters', 'litigation'],
            'Paralegal' => ['dashboard', 'messages', 'intakes', 'matters', 'litigation'],
            'Recoveries Manager' => ['dashboard', 'messages', 'recoveries'],
            'Recovery Officer' => ['dashboard', 'messages'],
            'Accountant' => ['dashboard', 'messages', 'finance', 'expenses', 'petty-cash', 'ledger', 'requisitions'],
            'HR Manager' => ['dashboard', 'messages', 'hr', 'staff', 'leave'],
            'Front Desk' => ['dashboard', 'messages', 'clients', 'intakes'],
            'IT Manager' => ['dashboard', 'messages', 'access', 'settings', 'branches', 'holidays'],
        ];

        foreach ($roleModuleMap as $roleName => $modules) {
            $perms = in_array($roleName, ['Super Admin', 'Administrator'], true)
                ? $allPermissions
                : $registry->permissionsForModules($modules);
            Role::create(['name' => $roleName, 'guard_name' => 'web'])->syncPermissions($perms);
        }

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
            ['leave.index', 'leave.create', 'leave.store', 'leave.show', 'leave.cancel'],
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
            ['recoveries.mine', 'recoveries.show', 'recoveries.activities.store'],
            $allPermissions
        ));

        Role::findByName('Recovery Officer')->givePermissionTo($recoveryOfficerSelfService);

        $kampala = Branch::firstOrCreate(['name' => 'Kampala'], ['city' => 'Kampala']);
        Branch::firstOrCreate(['name' => 'Mbarara'], ['city' => 'Mbarara']);
        Branch::firstOrCreate(['name' => 'Mbale'], ['city' => 'Mbale']);

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

        foreach ([
            'Stanbic Bank' => ['Stanbic NPL', 'Stanbic Write Off'],
            'DFCU Bank' => ['DFCU NPL', 'DFCU Write Off'],
            'Bank of Africa' => ['BOA'],
            'Uganda Development Bank' => ['UDB'],
            'Centenary Bank' => ['NPL', 'Write Off'],
        ] as $client => $portfolioTypes) {
            RecoveryClient::updateOrCreate(
                ['name' => $client],
                ['portfolio_types' => $portfolioTypes, 'is_active' => true]
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

        $practiceArea = PracticeArea::where('name', 'Litigation')->first() ?? PracticeArea::first();

        $pendingIntake = ClientIntake::firstOrCreate(
            ['client_name' => 'Demo Pending Client', 'legal_issue' => 'Employment termination advice'],
            [
                'intake_no' => MonthlyReferenceNumber::make(ClientIntake::class, 'intake_no', 'CI'),
                'client_type' => 'individual',
                'email' => 'pending.client@example.test',
                'phone' => '+256 700 100001',
                'address' => 'Kampala',
                'practice_area_id' => $practiceArea?->id,
                'preferred_lawyer_id' => $admin->id,
                'created_by' => $admin->id,
                'urgency' => 'urgent',
                'referral_source' => 'walk_in',
                'referral_name' => 'Front Desk',
                'referral_contact' => '+256 700 100001',
                'summary' => 'Demo intake waiting for review decision.',
                'status' => 'pending_review',
                'review_decision' => 'pending',
                'consultation_on' => now()->addDay()->toDateString(),
                'consultation_at' => '10:00',
            ]
        );

        $pendingIntake->conflictParties()->firstOrCreate(
            ['name' => 'Demo Employer Ltd'],
            ['relationship' => 'Opponent', 'contact' => '+256 700 200001', 'notes' => 'Former employer named in the employment dispute.']
        );

        $approvedClient = Client::firstOrCreate(
            ['email' => 'approved.client@example.test'],
            [
                'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
                'client_type' => 'individual',
                'name' => 'Demo Approved Client',
                'first_name' => 'Demo',
                'last_name' => 'Approved Client',
                'phone' => '+256 700 100002',
                'address' => 'Kampala Road',
                'client_in_charge_id' => $admin->id,
                'status' => 'active',
            ]
        );

        $approvedIntake = ClientIntake::firstOrCreate(
            ['client_name' => 'Demo Approved Client', 'legal_issue' => 'Commercial contract review'],
            [
                'client_id' => $approvedClient->id,
                'intake_no' => MonthlyReferenceNumber::make(ClientIntake::class, 'intake_no', 'CI'),
                'client_type' => 'individual',
                'email' => $approvedClient->email,
                'phone' => $approvedClient->phone,
                'address' => $approvedClient->address,
                'practice_area_id' => $practiceArea?->id,
                'preferred_lawyer_id' => $admin->id,
                'created_by' => $admin->id,
                'reviewed_by' => $superAdmin->id,
                'urgency' => 'normal',
                'referral_source' => 'existing_client',
                'referral_name' => 'Demo Existing Client',
                'referral_contact' => 'referrer@example.test',
                'summary' => 'Demo approved intake. A file is opened from the approved client register.',
                'status' => 'approved',
                'review_decision' => 'approved',
                'review_notes' => 'Demo intake approved for client registration.',
                'reviewed_at' => now(),
                'consultation_on' => now()->toDateString(),
                'consultation_at' => '14:00',
            ]
        );

        $demoMatter = Matter::firstOrCreate(
            ['title' => 'Commercial contract review', 'client_id' => $approvedClient->id],
            [
                'practice_area_id' => $practiceArea?->id,
                'opened_by' => $admin->id,
                'branch_id' => $kampala->id,
                'department_id' => $financeDepartmentId,
                'reference_no' => MonthlyReferenceNumber::make(Matter::class, 'reference_no', 'MT'),
                'opened_on' => now()->toDateString(),
                'privacy_status' => 'public',
                'status' => 'open',
                'description' => 'Demo matter opened from a client file.',
            ]
        );

        File::firstOrCreate(
            ['matter_id' => $demoMatter->id],
            [
                'client_id' => $approvedClient->id,
                'created_by' => $admin->id,
                'file_number' => MonthlyReferenceNumber::make(File::class, 'file_number', 'FL'),
                'file_name' => $demoMatter->title,
                'agreed_fee_amount' => 2500000,
            ]
        );

        $demoMatter->assignments()->firstOrCreate(
            ['user_id' => $admin->id, 'assignment_role' => 'partner'],
            ['assigned_on' => now()->toDateString(), 'is_lead' => true]
        );

        $approvedIntake->update([
            'client_id' => $approvedClient->id,
        ]);
    }
}
