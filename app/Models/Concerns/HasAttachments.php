<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function addAttachment(UploadedFile $file, array $attributes = [], ?string $disk = null): Attachment
    {
        $disk = $disk ?: config('filesystems.default');
        $path = $file->store($this->attachmentDirectory(), $disk);

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
}
