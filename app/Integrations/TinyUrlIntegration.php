<?php

namespace App\Integrations;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TinyUrlIntegration extends AbstractIntegration
{
    public static function service(): string
    {
        return 'tinyurl';
    }

    public static function label(): string
    {
        return 'TinyURL';
    }

    public static function description(): string
    {
        return 'Shorten ActiveCollab deep links in notifications using TinyURL.';
    }

    public static function logoIcon(): string
    {
        return 'link';
    }

    public static function credentialFields(): array
    {
        return [
            [
                'name' => 'api_token',
                'label' => 'API Token',
                'type' => 'password',
                'required' => true,
            ],
        ];
    }

    public static function setupSteps(): array
    {
        return [
            'Log in to your TinyURL account at tinyurl.com.',
            'Go to Settings → Developer → API Tokens.',
            'Click "Create new token" and give it a name.',
            'Copy the generated token and paste it into the API Token field above.',
        ];
    }

    public function testConnection(array $credentials): bool
    {
        if (empty($credentials['api_token'])) {
            return false;
        }

        try {
            Http::withToken($credentials['api_token'])
                ->get('https://api.tinyurl.com/user')
                ->throw();

            return true;
        } catch (RequestException $e) {
            Log::warning('TinyURL connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
