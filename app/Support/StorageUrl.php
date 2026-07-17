<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageUrl
{
    public static function for(?string $path, ?string $disk = null): ?string
    {
        if (! $path) {
            return null;
        }

        $diskName = $disk ?: config('filesystems.default', 'local');
        $storage = Storage::disk($diskName);

        if (self::usesTemporaryUrls($diskName)) {
            try {
                return $storage->temporaryUrl($path, now()->addMinutes(60));
            } catch (Throwable) {
                // Fall back to the configured URL for public buckets.
            }
        }

        try {
            return $storage->url($path);
        } catch (Throwable) {
            return asset('storage/'.ltrim($path, '/'));
        }
    }

    public static function profileDisk(): string
    {
        return config('jetstream.profile_photo_disk', 'public') ?: 'public';
    }

    private static function usesTemporaryUrls(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }
}
