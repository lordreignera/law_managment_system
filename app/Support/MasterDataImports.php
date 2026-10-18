<?php

namespace App\Support;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\CentenaryRegion;
use App\Models\Court;
use App\Models\CurrencyType;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MasterDataImports
{
    private const RELATIONS = [
        'bank_id' => ['bank_name', Bank::class, 'name'],
        'court_id' => ['court_name', Court::class, 'name'],
        'centenary_region_id' => ['centenary_region_name', CentenaryRegion::class, 'name'],
        'branch_id' => ['branch_name', Branch::class, 'name'],
        'currency_type_id' => ['currency_iso_code', CurrencyType::class, 'iso_code'],
    ];

    public function definitions(): array
    {
        return config('system_settings') + [
            'branches' => ['title' => 'Firm Branches', 'model' => Branch::class],
            'users' => ['title' => 'Staff Users', 'model' => User::class],
            'roles' => ['title' => 'Roles', 'model' => Role::class],
        ];
    }

    public function authorize(string $setting, User $actor): void
    {
        abort_unless(isset($this->definitions()[$setting]), 404);
        $permission = match ($setting) {
            'branches' => 'branches.store',
            'users' => 'access.users.store',
            'roles' => 'access.roles.store',
            default => 'settings.system.store',
        };
        abort_unless($actor->can($permission), 403);
    }

    public function headings(string $setting): array
    {
        return match ($setting) {
            'branches' => ['name', 'city', 'address', 'is_active'],
            'users' => ['name', 'email', 'password', 'phone', 'job_title', 'branch_name', 'department_name', 'role', 'joined_on'],
            'roles' => ['name', 'permissions'],
            default => array_merge(['name'], array_map(fn ($field) => self::RELATIONS[$field][0] ?? $field, $this->definitions()[$setting]['extra_fields'] ?? []), ['description', 'sort_order', 'is_active']),
        };
    }

    /**
     * Build a query for exporting the current approved reference data.
     *
     * The export uses the same column order as the import template, so an
     * exported list can be reviewed, amended, and imported again.
     */
    public function exportQuery(string $setting)
    {
        $definition = $this->definitions()[$setting] ?? abort(404);

        return match ($setting) {
            'users' => User::query()->with(['branch', 'department', 'staffProfile', 'roles'])->orderBy('id'),
            'roles' => Role::query()->with('permissions')->orderBy('name'),
            default => $definition['model']::query()
                ->with($this->exportRelations($definition['extra_fields'] ?? []))
                ->orderBy('id'),
        };
    }

    public function exportRow(string $setting, object $record): array
    {
        if ($setting === 'branches') {
            return [$record->name, $record->city, $record->address, $record->is_active ? 1 : 0];
        }

        if ($setting === 'users') {
            return [
                $record->name,
                $record->email,
                '', // Never export password hashes; the import requires a new password.
                $record->staffProfile?->phone,
                $record->staffProfile?->job_title,
                $record->branch?->name,
                $record->department?->name,
                $record->roles->pluck('name')->implode('|'),
                optional($record->staffProfile?->joined_on)->format('Y-m-d'),
            ];
        }

        if ($setting === 'roles') {
            return [$record->name, $record->permissions->pluck('name')->implode('|')];
        }

        $definition = $this->definitions()[$setting];
        $row = [$record->name];
        foreach ($definition['extra_fields'] ?? [] as $field) {
            if (isset(self::RELATIONS[$field])) {
                $relation = match ($field) {
                    'bank_id' => 'bank',
                    'court_id' => 'court',
                    'centenary_region_id' => 'region',
                    'branch_id' => 'branch',
                    'currency_type_id' => 'currencyType',
                };
                $row[] = $record->{$relation}?->{self::RELATIONS[$field][2]};
            } elseif ($field === 'portfolio_types') {
                $row[] = is_array($record->{$field}) ? implode('|', $record->{$field}) : $record->{$field};
            } elseif (in_array($field, ['is_default', 'is_inclusive', 'carry_forward', 'requires_attachment'], true)) {
                $row[] = $record->{$field} ? 1 : 0;
            } else {
                $row[] = $record->{$field};
            }
        }

        return array_merge($row, [
            $record->description,
            $record->sort_order,
            $record->is_active ? 1 : 0,
        ]);
    }

    private function exportRelations(array $fields): array
    {
        return collect($fields)->map(fn (string $field) => match ($field) {
            'bank_id' => 'bank',
            'court_id' => 'court',
            'centenary_region_id' => 'region',
            'branch_id' => 'branch',
            'currency_type_id' => 'currencyType',
            default => null,
        })->filter()->unique()->values()->all();
    }

    public function import(string $setting, Collection $rows, User $actor): int
    {
        $this->authorize($setting, $actor);
        if ($rows->count() > 2000) {
            throw ValidationException::withMessages(['file' => 'Import at most 2,000 master-data rows at a time.']);
        }

        return DB::transaction(function () use ($rows, $setting) {
            $count = 0;
            foreach ($rows as $index => $row) {
                $input = collect($row)->only($this->headings($setting))->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
                if (! collect($input)->contains(fn ($value) => $value !== null && $value !== '')) {
                    continue;
                }
                try {
                    $this->importRow($setting, $input);
                    $count++;
                } catch (ValidationException $exception) {
                    $message = collect($exception->errors())->flatten()->join(' ');
                    throw ValidationException::withMessages(['file' => 'Row '.($index + 2).': '.$message]);
                }
            }

            return $count;
        });
    }

    private function importRow(string $setting, array $input): void
    {
        if ($setting === 'users') {
            $this->importUser($input);

            return;
        }
        if ($setting === 'roles') {
            $input['permissions'] = $this->list($input['permissions'] ?? '');
            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:191', Rule::notIn(['Super Admin', 'Administrator', 'Client']), 'unique:roles,name'],
                'permissions' => ['array'],
                'permissions.*' => ['string', Rule::in(app(RoutePermissionRegistry::class)->routeNames()->all())],
            ])->validate();
            $permissions = collect($data['permissions'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
            Role::create(['name' => $data['name'], 'guard_name' => 'web'])->syncPermissions($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }
        $input['is_active'] = $this->boolean($input['is_active'] ?? '1');
        if ($setting === 'branches') {
            $record = Branch::firstOrNew(['name' => $input['name'] ?? '']);
            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:191', Rule::unique('branches', 'name')->ignore($record->id)],
                'city' => ['nullable', 'string', 'max:191'],
                'address' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['boolean'],
            ])->validate();
            $record->fill($data)->save();

            return;
        }
        $definition = $this->definitions()[$setting];
        $keys = ['name' => $input['name'] ?? ''];
        foreach (self::RELATIONS as $field => [$heading, $model, $column]) {
            if (! in_array($field, $definition['extra_fields'] ?? [], true)) {
                continue;
            }
            $input[$field] = empty($input[$heading]) ? null : $model::where($column, $input[$heading])->where('is_active', true)->value('id');
            if (! empty($input[$heading]) && ! $input[$field]) {
                throw ValidationException::withMessages([$heading => 'Select an existing active '.$heading.'; import parent records first.']);
            }
            if (in_array($field, ['court_id', 'bank_id', 'centenary_region_id'], true)) {
                $keys[$field] = $input[$field];
            }
        }
        if ($setting === 'notification-templates') {
            $keys = ['event_key' => $input['event_key'] ?? ''];
        }
        foreach (['is_default', 'is_inclusive', 'carry_forward', 'requires_attachment'] as $field) {
            if (in_array($field, $definition['extra_fields'] ?? [], true)) {
                $input[$field] = $this->boolean($input[$field] ?? '0');
            }
        }
        if (isset($input['portfolio_types'])) {
            $input['portfolio_types'] = implode("\n", $this->list($input['portfolio_types']));
        }
        $record = $definition['model']::firstOrNew($keys);
        $data = app(MasterDataValidator::class)->validate($input, $definition, $record->exists ? $record : null);
        $record->fill($data)->save();
        if ($setting === 'letterheads' && ($data['is_default'] ?? false)) {
            $definition['model']::whereKeyNot($record->id)->update(['is_default' => false]);
        }
    }

    private function importUser(array $input): void
    {
        $branch = Branch::where('name', $input['branch_name'] ?? '')->where('is_active', true)->first();
        $department = Department::where('name', $input['department_name'] ?? '')->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branch?->id))->first();
        $input['branch_id'] = $branch?->id;
        $input['department_id'] = $department?->id;
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['required', 'string', 'max:60'],
            'job_title' => ['required', ReferenceData::jobTitleRule()],
            'branch_id' => ['required', 'exists:branches,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'role' => ['required', Rule::exists('roles', 'name')->whereNotIn('name', ['Super Admin', 'Administrator', 'Client'])],
            'joined_on' => ['nullable', 'date'],
        ])->validate();
        $user = User::create(collect($data)->only(['name', 'email', 'branch_id', 'department_id'])->all() + ['account_type' => 'staff', 'password' => Hash::make($data['password'])]);
        $user->markEmailAsVerified();
        $user->assignRole($data['role']);
        StaffProfile::create(collect($data)->only(['phone', 'job_title', 'branch_id', 'department_id', 'joined_on'])->all() + ['user_id' => $user->id, 'employment_status' => 'active', 'requested_role' => $data['role']]);
    }

    private function list(string $value): array
    {
        return collect(preg_split('/[|\r\n]+/', $value))->map(fn ($item) => trim($item))->filter()->unique()->values()->all();
    }

    private function boolean(mixed $value): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }
}
