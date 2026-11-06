<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kfms:generate-vapid-keys', function (): int {
    try {
        $keys = VAPID::createVapidKeys();
    } catch (Throwable) {
        $this->error('Could not generate VAPID keys. Check that PHP OpenSSL is enabled and its configuration file is available.');

        return 1;
    }

    $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
    $this->comment('Store these values securely in the application environment. Do not commit the private key.');

    return 0;
})->purpose('Generate a VAPID key pair for installed-app push notifications');
