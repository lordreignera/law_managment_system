<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\RecoveryClient;
use App\Models\User;
use App\Models\ZonalOffice;
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
            'manage matters',
            'manage litigation',
            'manage recoveries',
            'manage land titles',
            'manage finance',
            'manage staff',
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
            'Senior Partner' => ['view dashboard', 'manage clients', 'manage matters', 'manage litigation', 'approve requests'],
            'Advocate' => ['view dashboard', 'manage clients', 'manage matters', 'manage litigation'],
            'Paralegal' => ['view dashboard', 'manage matters', 'manage litigation'],
            'Recoveries Manager' => ['view dashboard', 'manage recoveries', 'approve requests'],
            'Recovery Officer' => ['view dashboard', 'manage recoveries'],
            'Accountant' => ['view dashboard', 'manage finance'],
            'HR Manager' => ['view dashboard', 'manage staff', 'approve requests'],
            'Front Desk' => ['view dashboard', 'manage clients', 'manage matters'],
            'IT Manager' => ['view dashboard', 'manage settings'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName)->syncPermissions($rolePermissions);
        }

        $kampala = Branch::firstOrCreate(['code' => 'KLA'], ['name' => 'Kampala', 'city' => 'Kampala']);
        Branch::firstOrCreate(['code' => 'MBR'], ['name' => 'Mbarara', 'city' => 'Mbarara']);
        Branch::firstOrCreate(['code' => 'MBL'], ['name' => 'Mbale', 'city' => 'Mbale']);

        foreach ([
            ['code' => 'LIT', 'name' => 'Litigation'],
            ['code' => 'REC', 'name' => 'Recoveries'],
            ['code' => 'SEC', 'name' => 'Securities and Land Titles'],
            ['code' => 'FIN', 'name' => 'Finance and Administration'],
            ['code' => 'HR', 'name' => 'Human Resources'],
        ] as $department) {
            Department::firstOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name'], 'branch_id' => $kampala->id]
            );
        }

        foreach ([
            ['code' => 'STANBIC', 'name' => 'Stanbic Bank'],
            ['code' => 'DFCU', 'name' => 'DFCU Bank'],
            ['code' => 'BOA', 'name' => 'Bank of Africa'],
            ['code' => 'UDB', 'name' => 'Uganda Development Bank'],
            ['code' => 'CENTENARY', 'name' => 'Centenary Bank'],
        ] as $client) {
            RecoveryClient::firstOrCreate(['code' => $client['code']], ['name' => $client['name']]);
        }

        foreach (['KCCA', 'Jinja', 'Mukono', 'Wakiso Kyadondo', 'Wakiso Busiro', 'Masaka', 'Mbarara', 'Lira', 'Kabarole', 'Mbale', 'Arua', 'Gulu', 'Tororo'] as $office) {
            ZonalOffice::firstOrCreate(['name' => $office]);
        }

        $financeDepartmentId = Department::where('code', 'FIN')->value('id');

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
    }
}
