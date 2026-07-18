<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Keep Livewire's temporary uploads away from the default document bucket.
    | The application stores documents on S3/R2, but profile photos and
    | signatures must first land locally before Jetstream saves them.
    |
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', 'local'),
        'rules' => ['required', 'file', 'max:10240'],
        'directory' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DIRECTORY', 'livewire-tmp'),
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];
