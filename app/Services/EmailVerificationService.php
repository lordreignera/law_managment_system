<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationService
{
    public const COOLDOWN_SECONDS = 60;

    public function remaining(User $user): int
    {
        return $user->hasVerifiedEmail() ? 0 : max(0, RateLimiter::availableIn($this->key($user)));
    }

    /** @return array{status: string, message: string, retry_after: int} */
    public function send(User $user, ?Closure $sender = null): array
    {
        if ($user->hasVerifiedEmail()) {
            return ['status' => 'verified', 'message' => 'Your email is already verified.', 'retry_after' => 0];
        }

        $result = Cache::lock($this->key($user).':sending', 120)->get(function () use ($user, $sender) {
            $remaining = $this->remaining($user);

            if ($remaining > 0) {
                return ['status' => 'wait', 'message' => 'Please wait before requesting another verification email.', 'retry_after' => $remaining];
            }

            try {
                if ($sender) {
                    $sender();
                } else {
                    $user->sendEmailVerificationNotification();
                }
            } catch (\Throwable $exception) {
                report($exception);

                return ['status' => 'failed', 'message' => 'We could not send the verification email. Please try again shortly or contact the firm.', 'retry_after' => 0];
            }

            RateLimiter::hit($this->key($user), self::COOLDOWN_SECONDS);

            return ['status' => 'sent', 'message' => 'A new verification link has been sent. Check your inbox and spam folder.', 'retry_after' => self::COOLDOWN_SECONDS];
        });

        return $result ?: ['status' => 'wait', 'message' => 'A verification email is already being sent. Please wait.', 'retry_after' => 1];
    }

    private function key(User $user): string
    {
        return 'verification-email:'.$user->getKey().':'.hash('sha256', strtolower(trim($user->email)));
    }
}
