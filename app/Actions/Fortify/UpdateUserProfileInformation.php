<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\EmailVerificationService;
use App\Support\StorageUrl;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ])->validateWithBag('updateProfileInformation');

        if ($user->isClientAccount() && strtolower(trim($input['email'])) !== strtolower(trim($user->email))) {
            throw ValidationException::withMessages([
                'email' => 'Please contact the firm to change the registered email for your client portal.',
            ])->errorBag('updateProfileInformation');
        }

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if (isset($input['signature'])) {
            if ($user->signature_path) {
                Storage::disk(StorageUrl::profileDisk())->delete($user->signature_path);
            }

            $user->forceFill([
                'signature_path' => $input['signature']->store('signatures', StorageUrl::profileDisk()),
            ])->save();
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        session()->flash('profile_verification_result', app(EmailVerificationService::class)->send($user));
    }
}
