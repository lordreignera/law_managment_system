<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EmailVerificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\LoginRequest;
use Spatie\Permission\Models\Role;

class ClientPortalAuthController extends Controller
{
    public function login()
    {
        return view('auth.login', ['portal' => 'client']);
    }

    public function authenticate(LoginRequest $request, AuthenticatedSessionController $sessions)
    {
        $currentUser = $request->user();

        if ($currentUser) {
            if ($currentUser->staffProfile?->employment_status === 'active') {
                app(AttendanceService::class)->checkOut($currentUser, $request, 'client-portal-switch');
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $sessions->store($request);
    }

    public function welcome(Request $request)
    {
        if ($request->user()?->isClientAccount()) {
            return redirect()->route('client.dashboard');
        }

        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('client-portal.auth.welcome');
    }

    public function create()
    {
        return view('client-portal.auth.register');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
        ]);

        $email = strtolower(trim($data['email']));
        $client = Client::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'active')
            ->first();

        if (! $client) {
            return response()->json([
                'exists' => false,
                'message' => 'This email is not registered as an active client with the firm.',
            ], 404);
        }

        return response()->json([
            'exists' => true,
            'phone_hint' => $this->phoneHint($client->phone),
            'has_portal_account' => $client->portalAccount()->exists(),
            'message' => $client->portalAccount()->exists()
                ? 'A portal account already exists for this client. Please sign in or use forgot password.'
                : ($client->phone ? 'Client record found. Your registered phone number is partly hidden until you verify your email.' : 'Client record found. No phone number is recorded with the firm.'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $email = strtolower(trim($data['email']));
        $client = Client::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'active')
            ->first();

        if (! $client) {
            throw ValidationException::withMessages([
                'email' => 'This email is not registered with Kalikumutima & Co Advocates. Please contact the firm before creating portal access.',
            ]);
        }

        if ($client->portalAccount()->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A client portal account already exists for this client. Please sign in or use forgot password.',
            ]);
        }

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email already has an account. Please sign in or contact the firm.',
            ]);
        }

        $user = DB::transaction(function () use ($client, $email, $data) {
            $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);

            if ($lockedClient->status !== 'active' || strtolower(trim($lockedClient->email)) !== $email || $lockedClient->portalAccount()->exists()
                || User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Portal access is already registered or this client is no longer active. Please sign in or contact the firm.',
                ]);
            }

            $user = User::create([
                'name' => $lockedClient->display_name,
                'email' => $email,
                'account_type' => 'client',
                'password' => Hash::make($data['password']),
            ]);

            if (Role::where('name', 'Client')->exists()) {
                $user->assignRole('Client');
            }

            ClientPortalAccount::create([
                'client_id' => $lockedClient->id,
                'user_id' => $user->id,
                'registered_email' => $email,
                'registered_phone' => $lockedClient->phone,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $verification = app(EmailVerificationService::class)->send($user, fn () => event(new Registered($user)));

        return redirect()
            ->route('verification.notice')
            ->with('status', $verification['status'] === 'sent' ? 'verification-link-sent' : 'verification-delivery-failed');
    }

    private function phoneHint(?string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        return $phone === '' ? null : '••••'.(strlen($phone) > 4 ? ' '.substr($phone, -4) : '');
    }
}
