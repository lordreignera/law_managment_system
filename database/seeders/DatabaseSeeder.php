<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Engagement;
use App\Models\Matter;
use App\Models\PracticeArea;
use App\Models\RecoveryClient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\ZonalOffice;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            LeaveTypeSeeder::class,
            PaymentModeSeeder::class,
            EngagementTypeSeeder::class,
            CurrencyTypeSeeder::class,
            RequisitionCategorySeeder::class,
            ExpenseCategorySeeder::class,
            LetterheadSeeder::class,
            BillableRateSeeder::class,
        ]);

        $permissions = [
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

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $roles = [
            'Super Admin' => $permissions,
            'Administrator' => $permissions,
            'Managing Partner' => $permissions,
            'Senior Partner' => ['view dashboard', 'manage clients', 'manage intakes', 'manage matters', 'manage litigation', 'approve requests'],
            'Advocate' => ['view dashboard', 'manage clients', 'manage intakes', 'manage matters', 'manage litigation'],
            'Paralegal' => ['view dashboard', 'manage intakes', 'manage matters', 'manage litigation'],
            'Recoveries Manager' => ['view dashboard', 'manage recoveries', 'approve requests'],
            'Recovery Officer' => ['view dashboard', 'manage recoveries'],
            'Accountant' => ['view dashboard', 'manage finance'],
            'HR Manager' => ['view dashboard', 'manage staff', 'approve requests'],
            'Front Desk' => ['view dashboard', 'manage clients', 'manage intakes', 'manage matters'],
            'IT Manager' => ['view dashboard', 'manage access control', 'manage settings'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName)->syncPermissions($rolePermissions);
        }

        $kampala = Branch::firstOrCreate(['name' => 'Kampala'], ['city' => 'Kampala']);
        Branch::firstOrCreate(['name' => 'Mbarara'], ['city' => 'Mbarara']);
        Branch::firstOrCreate(['name' => 'Mbale'], ['city' => 'Mbale']);

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

        foreach (['Stanbic Bank', 'DFCU Bank', 'Bank of Africa', 'Uganda Development Bank', 'Centenary Bank'] as $client) {
            RecoveryClient::firstOrCreate(['name' => $client]);
        }

        foreach (['KCCA', 'Jinja', 'Mukono', 'Wakiso Kyadondo', 'Wakiso Busiro', 'Masaka', 'Mbarara', 'Lira', 'Kabarole', 'Mbale', 'Arua', 'Gulu', 'Tororo'] as $office) {
            ZonalOffice::firstOrCreate(['name' => $office]);
        }

        $financeDepartmentId = Department::where('name', 'Finance and Administration')->value('id');

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@kalikumutima.test'],
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
                'referral_source' => 'Walk-in',
                'summary' => 'Demo intake waiting for conflict review.',
                'status' => 'conflict_check',
                'conflict_status' => 'pending',
                'consultation_on' => now()->addDay()->toDateString(),
                'consultation_at' => '10:00',
            ]
        );

        $pendingIntake->conflictParties()->firstOrCreate(
            ['name' => 'Demo Employer Ltd'],
            ['relationship' => 'Opponent', 'notes' => 'Former employer named in the employment dispute.']
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

        $clearedIntake = ClientIntake::firstOrCreate(
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
                'referral_source' => 'Existing relationship',
                'summary' => 'Demo cleared intake already converted into an engagement-pending matter.',
                'status' => 'engagement_pending',
                'conflict_status' => 'cleared',
                'conflict_notes' => 'Demo conflict review cleared.',
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
                'status' => 'engagement_pending',
                'description' => 'Demo matter waiting for engagement review and client acceptance.',
            ]
        );

        Engagement::firstOrCreate(
            ['matter_id' => $demoMatter->id],
            [
                'client_id' => $approvedClient->id,
                'created_by' => $admin->id,
                'engagement_no' => MonthlyReferenceNumber::make(Engagement::class, 'engagement_no', 'EG'),
                'title' => $demoMatter->title,
                'status' => 'pending',
            ]
        );

        $demoMatter->assignments()->firstOrCreate(
            ['user_id' => $admin->id, 'assignment_role' => 'partner'],
            ['assigned_on' => now()->toDateString(), 'is_lead' => true]
        );

        $clearedIntake->update([
            'client_id' => $approvedClient->id,
            'converted_matter_id' => $demoMatter->id,
        ]);
    }
}
