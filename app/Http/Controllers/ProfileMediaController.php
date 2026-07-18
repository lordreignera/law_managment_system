<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\StorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileMediaController extends Controller
{
    public function photo(Request $request, User $user): StreamedResponse
    {
        $this->authorizeUserMedia($request, $user);

        return $this->stream($user->profile_photo_path);
    }

    public function signature(Request $request, User $user): StreamedResponse
    {
        $this->authorizeUserMedia($request, $user);

        return $this->stream($user->signature_path);
    }

    private function authorizeUserMedia(Request $request, User $user): void
    {
        abort_unless($request->user()?->is($user), 403);
    }

    private function stream(?string $path): StreamedResponse
    {
        abort_unless($path, 404);

        $disk = Storage::disk(StorageUrl::profileDisk());

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
