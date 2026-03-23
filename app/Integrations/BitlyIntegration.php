<?php

namespace App\Integrations;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitlyIntegration extends AbstractIntegration
{
    public static function service(): string
    {
        return 'bitly';
    }

    public static function label(): string
    {
        return 'Bitly';
    }

    public static function description(): string
    {
        return 'Shorten ActiveCollab deep links in notifications using Bitly.';
    }

    public static function logoIcon(): string
    {
        return 'link';
    }

    public static function credentialFields(): array
    {
        return [
            [
                'name' => 'access_token',
                'label' => 'Access Token',
                'type' => 'password',
                'required' => true,
            ],
        ];
    }

    public static function setupSteps(): array
    {
        return [
            'Log in to your Bitly account at bitly.com.',
            'Go to Settings → API → Access Token.',
            'Enter your password to reveal or generate your access token.',
            'Copy the token and paste it into the Access Token field above.',
        ];
    }

    public function testConnection(array $credentials): bool
    {
        if (empty($credentials['access_token'])) {
            return false;
        }

        try {
            Http::withToken($credentials['access_token'])
                ->get('https://api-ssl.bitly.com/v4/user')
                ->throw();

            return true;
        } catch (RequestException $e) {
            Log::warning('Bitly connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
