<?php

namespace App\Integrations;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActiveCollabIntegration extends AbstractIntegration
{
    public static function service(): string
    {
        return 'activecollab';
    }

    public static function label(): string
    {
        return 'ActiveCollab';
    }

    public static function description(): string
    {
        return 'Receive project and task updates from ActiveCollab via webhooks.';
    }

    public static function logoIcon(): string
    {
        return 'monitor';
    }

    public static function credentialFields(): array
    {
        return [
            [
                'name' => 'url',
                'label' => 'ActiveCollab URL',
                'type' => 'url',
                'required' => true,
                'placeholder' => 'https://app.activecollab.com/123456',
            ],
            [
                'name' => 'token',
                'label' => 'API Token',
                'type' => 'password',
                'required' => true,
            ],
            [
                'name' => 'webhook_secret',
                'label' => 'Webhook Secret',
                'type' => 'password',
                'required' => true,
                'hint' => 'Required — used to verify that incoming webhooks are from ActiveCollab. Set the same value in ActiveCollab under Admin → Webhooks.',
            ],
        ];
    }

    public static function setupSteps(): array
    {
        return [
            'Log in to your ActiveCollab account as an administrator.',
            'Go to Admin → API → Token Generator.',
            'Enter a token name (e.g. "Nudge") and click Generate.',
            'Copy the generated token and paste it into the API Token field above.',
            'Set a Webhook Secret and enter the same value in ActiveCollab under Admin → Webhooks to verify payload authenticity.',
            'After saving, copy the Webhook URL shown below and add it in ActiveCollab under Admin → Webhooks.',
        ];
    }

    public static function hasWebhook(): bool
    {
        return true;
    }

    public static function webhookRouteParam(): ?string
    {
        return 'webhookToken';
    }

    public function testConnection(array $credentials): bool
    {
        if (empty($credentials['url']) || empty($credentials['token'])) {
            return false;
        }

        try {
            Http::withToken($credentials['token'])
                ->get(rtrim($credentials['url'], '/').'/api/v1/users/me')
                ->throw();

            return true;
        } catch (RequestException $e) {
            Log::warning('ActiveCollab connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
