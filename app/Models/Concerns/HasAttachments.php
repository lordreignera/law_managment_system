<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function addAttachment(UploadedFile $file, array $attributes = [], ?string $disk = null): Attachment
    {
        $disk = $disk ?: config('filesystems.default');
        $this->ensureUploadDiskIsReady($disk);

        try {
            $path = $file->store($this->attachmentDirectory(), $disk);
        } catch (Throwable $exception) {
            report($exception);

            throw $this->uploadValidationException();
        }

        if (! $path) {
            throw $this->uploadValidationException();
        }

        return $this->attachments()->create(array_merge([
            'uploaded_by' => Auth::id(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ], $attributes));
    }

    protected function attachmentDirectory(): string
    {
        return 'attachments/'.str(class_basename($this))->snake()->plural();
    }

    private function ensureUploadDiskIsReady(string $disk): void
    {
        $config = config("filesystems.disks.{$disk}", []);

        if (($config['driver'] ?? null) !== 's3') {
            return;
        }

        foreach (['key', 'secret', 'bucket', 'endpoint'] as $requiredSetting) {
            if (blank($config[$requiredSetting] ?? null)) {
                throw ValidationException::withMessages([
                    'attachment' => 'Document upload is not ready. Please set the R2/S3 access key, secret, bucket, and endpoint, then clear the Laravel config cache.',
                    'attachments' => 'Document upload is not ready. Please set the R2/S3 access key, secret, bucket, and endpoint, then clear the Laravel config cache.',
                    'documents' => 'Document upload is not ready. Please set the R2/S3 access key, secret, bucket, and endpoint, then clear the Laravel config cache.',
                ]);
            }
        }
    }

    private function uploadValidationException(): ValidationException
    {
        return ValidationException::withMessages([
            'attachment' => 'The document could not be uploaded to storage. Please confirm the R2/S3 credentials, bucket permissions, and endpoint.',
            'attachments' => 'The document could not be uploaded to storage. Please confirm the R2/S3 credentials, bucket permissions, and endpoint.',
            'documents' => 'The document could not be uploaded to storage. Please confirm the R2/S3 credentials, bucket permissions, and endpoint.',
        ]);
    }
}
