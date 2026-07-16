<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class ClientPortalAuthController extends Controller
{
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
            'client_no' => $client->client_no,
            'phone' => $client->phone,
            'has_portal_account' => $client->portalAccount()->exists(),
            'message' => $client->portalAccount()->exists()
                ? 'A portal account already exists for this client. Please sign in or use forgot password.'
                : 'Client record found. Your registered phone number has been filled in.',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:60'],
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

        if (! empty($data['phone']) && $client->phone && $this->normalizePhone($data['phone']) !== $this->normalizePhone($client->phone)) {
            throw ValidationException::withMessages([
                'phone' => 'The phone number does not match the client record held by the firm.',
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

        $user = User::create([
            'name' => $client->display_name,
            'email' => $email,
            'account_type' => 'client',
            'password' => Hash::make($data['password']),
        ]);

        if (Role::where('name', 'Client')->exists()) {
            $user->assignRole('Client');
        }

        $portalAccount = ClientPortalAccount::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'registered_email' => $email,
            'registered_phone' => $client->phone,
        ]);

        $verificationMailSent = true;

        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            $verificationMailSent = false;

            $user->markEmailAsVerified();
            $portalAccount->update(['verified_at' => now()]);

            Log::warning('Client portal verification email failed.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }

        Auth::login($user);

        if (! $verificationMailSent) {
            return redirect()
                ->route('client.dashboard')
                ->with('status', 'Your client record was confirmed and your portal is active. The verification email could not be sent because the mail server is not reachable.');
        }

        return redirect()
            ->route('verification.notice')
            ->with('status', 'We found your client record. Please verify your email to activate your client portal.');
    }

    private function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        return $phone === '' ? null : $phone;
    }
}
