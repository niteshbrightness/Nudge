<?php

namespace App\Integrations;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioIntegration extends AbstractIntegration
{
    public static function service(): string
    {
        return 'twilio';
    }

    public static function label(): string
    {
        return 'Twilio';
    }

    public static function description(): string
    {
        return 'Send SMS notifications to clients via Twilio.';
    }

    public static function logoIcon(): string
    {
        return 'message-square';
    }

    public static function credentialFields(): array
    {
        return [
            [
                'name' => 'sid',
                'label' => 'Account SID',
                'type' => 'password',
                'required' => true,
            ],
            [
                'name' => 'auth_token',
                'label' => 'Auth Token',
                'type' => 'password',
                'required' => true,
            ],
            [
                'name' => 'from',
                'label' => 'From Number',
                'type' => 'tel',
                'required' => true,
                'placeholder' => '+15550000000',
            ],
        ];
    }

    public static function setupSteps(): array
    {
        return [
            'Log in to the Twilio Console at console.twilio.com.',
            'On the dashboard, locate your Account SID and Auth Token.',
            'Click "Show" next to the Auth Token to reveal it.',
            'Copy both values and paste them into the fields above.',
            'Enter the Twilio phone number you want to send SMS from (e.g. +15550000000).',
        ];
    }

    public function testConnection(array $credentials): bool
    {
        if (empty($credentials['sid']) || empty($credentials['auth_token'])) {
            return false;
        }

        try {
            Http::withBasicAuth($credentials['sid'], $credentials['auth_token'])
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$credentials['sid']}.json")
                ->throw();

            return true;
        } catch (RequestException $e) {
            Log::warning('Twilio connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
