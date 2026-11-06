<?php

namespace App\Actions\Fortify;

use App\Models\StaffProfile;
use App\Models\User;
use App\Support\ReferenceData;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:60'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'job_title' => ['required', 'string', 'max:191', ReferenceData::jobTitleRule()],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where('branch_id', (int) ($input['branch_id'] ?? 0))
                    ->where('is_active', true)),
            ],
            'requested_role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->whereNotIn('name', ['Super Admin', 'Administrator'])),
            ],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'branch_id' => $input['branch_id'],
            'department_id' => $input['department_id'],
            'password' => Hash::make($input['password']),
        ]);
        StaffProfile::create([
            'user_id' => $user->id,
            'branch_id' => $input['branch_id'],
            'department_id' => $input['department_id'],
            'job_title' => $input['job_title'],
            'phone' => $input['phone'],
            'date_of_birth' => $input['date_of_birth'] ?? null,
            'employment_status' => 'pending',
            'requested_role' => $input['requested_role'],
        ]);

        return $user;
    }
}
