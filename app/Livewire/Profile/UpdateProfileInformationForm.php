<?php

namespace App\Livewire\Profile;

use App\Services\EmailVerificationService;
use App\Support\StorageUrl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;
use Livewire\WithFileUploads;

class UpdateProfileInformationForm extends Component
{
    use WithFileUploads;

    public array $state = [];

    public $photo;

    public $signature;

    public bool $verificationLinkSent = false;

    public string $verificationMessage = '';

    public string $verificationState = 'info';

    public int $verificationRetryAfter = 0;

    public function mount(): void
    {
        $user = Auth::user();
        $this->verificationRetryAfter = app(EmailVerificationService::class)->remaining($user);

        $this->state = array_merge([
            'email' => $user->email,
        ], $user->withoutRelations()->toArray());

        if ($result = session()->pull('profile_verification_result')) {
            $this->applyVerificationResult($result);
        }
    }

    public function updateProfileInformation(UpdatesUserProfileInformation $updater)
    {
        $this->resetErrorBag();

        $input = $this->state;

        if ($this->photo) {
            $input['photo'] = $this->photo;
        }

        if ($this->signature) {
            $input['signature'] = $this->signature;
        }

        $updater->update(Auth::user(), $input);

        Auth::setUser(Auth::user()->fresh());

        if ($result = session()->pull('profile_verification_result')) {
            $this->applyVerificationResult($result);
        }

        if ($this->photo || $this->signature) {
            return redirect()->route(Auth::user()->isClientAccount() ? 'client.profile' : 'profile.show');
        }

        $this->dispatch('saved');
        $this->dispatch('refresh-navigation-menu');
    }

    public function deleteProfilePhoto(): void
    {
        Auth::user()->deleteProfilePhoto();

        Auth::setUser(Auth::user()->fresh());

        $this->dispatch('refresh-navigation-menu');
    }

    public function deleteSignature(): void
    {
        $user = Auth::user();

        if ($user->signature_path) {
            Storage::disk(StorageUrl::profileDisk())->delete($user->signature_path);
        }

        $user->forceFill(['signature_path' => null])->save();

        Auth::setUser($user->fresh());

        $this->dispatch('saved');
    }

    public function sendEmailVerification(): void
    {
        $this->applyVerificationResult(app(EmailVerificationService::class)->send(Auth::user()));
    }

    private function applyVerificationResult(array $result): void
    {
        $this->verificationLinkSent = $result['status'] === 'sent';
        $this->verificationMessage = $result['message'];
        $this->verificationState = $result['status'] === 'failed' ? 'error' : ($this->verificationLinkSent ? 'success' : 'info');
        $this->verificationRetryAfter = $result['retry_after'];
        $this->dispatch('verification-resend', retryAfter: $this->verificationRetryAfter);
    }

    public function getUserProperty()
    {
        return Auth::user();
    }

    public function render()
    {
        return view('profile.update-profile-information-form');
    }
}
