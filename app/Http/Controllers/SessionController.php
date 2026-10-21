<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Return the current session token without exposing account details.
     *
     * This endpoint is intentionally a GET. It lets an open tab obtain the
     * token belonging to the session that is actually active before a form is
     * submitted. It never renews authentication or replays an action.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => (bool) $user,
            'user_id' => $user?->getAuthIdentifier(),
            'csrf_token' => $request->session()->token(),
        ])
            ->header('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
