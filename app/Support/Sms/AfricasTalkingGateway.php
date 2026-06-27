<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Http;

class AfricasTalkingGateway implements SmsGateway
{
    public function __construct(
        private readonly string $username,
        private readonly ?string $apiKey,
        private readonly ?string $senderId = null,
        private readonly bool $sandbox = true,
    ) {
    }

    public function send(array $recipients, string $message): SmsResult
    {
        $recipients = array_values(array_filter(array_map('trim', $recipients)));

        if ($recipients === []) {
            return SmsResult::failure('No recipients provided.');
        }

        $payload = [
            'username' => $this->username,
            'to' => implode(',', $recipients),
            'message' => $message,
        ];

        if ($this->senderId) {
            $payload['from'] = $this->senderId;
        }

        try {
            $response = Http::asForm()
                ->withHeaders([
                    'apiKey' => (string) $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->post($this->endpoint(), $payload);

            if ($response->failed()) {
                return SmsResult::failure(
                    "Africa's Talking request failed with HTTP {$response->status()}.",
                    $response->json() ?? []
                );
            }

            return SmsResult::success($recipients, $response->json() ?? []);
        } catch (\Throwable $e) {
            return SmsResult::failure($e->getMessage());
        }
    }

    private function endpoint(): string
    {
        return $this->sandbox
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';
    }
}
