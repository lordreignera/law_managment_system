<?php

namespace App\Actions\Fortify;

use App\Models\StaffProfile;
use App\Models\User;
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
            'job_title' => ['required', 'string', 'max:191'],
            'branch_id' => ['required', 'exists:branches,id'],
            'department_id' => ['required', 'exists:departments,id'],
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
            'employment_status' => 'pending',
            'requested_role' => $input['requested_role'],
        ]);

        return $user;
    }
}
