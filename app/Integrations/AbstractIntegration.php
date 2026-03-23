<?php

namespace App\Integrations;

use App\Integrations\Contracts\IntegrationInterface;

abstract class AbstractIntegration implements IntegrationInterface
{
    public static function hasWebhook(): bool
    {
        return false;
    }

    public static function webhookRouteParam(): ?string
    {
        return null;
    }

    public function testConnection(array $credentials): bool
    {
        return true;
    }
}
