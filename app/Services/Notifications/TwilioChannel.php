<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\NotificationChannelInterface;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TwilioChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly string $sid = '',
        private readonly string $authToken = '',
        private readonly string $from = '',
    ) {}

    public function send(Client $client, string $message): void
    {
        if (! $client->sms_consent) {
            throw new RuntimeException("SMS not sent to client #{$client->id}: no SMS consent.");
        }

        if (empty($this->sid) || empty($this->authToken) || empty($this->from)) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json";

        $response = Http::withBasicAuth($this->sid, $this->authToken)
            ->asForm()
            ->post($url, [
                'From' => $this->from,
                'To' => $client->phone,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            $error = $response->json('message', 'Unknown error');
            Log::error('Twilio send failed', ['client_id' => $client->id, 'error' => $error]);

            throw new RuntimeException("Twilio error: {$error}");
        }

        Log::info('Twilio SMS sent', ['client_id' => $client->id, 'to' => $client->phone]);
    }
}
