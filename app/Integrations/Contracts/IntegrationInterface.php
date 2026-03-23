<?php

namespace App\Integrations\Contracts;

interface IntegrationInterface
{
    public static function service(): string;

    public static function label(): string;

    public static function description(): string;

    public static function logoIcon(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function credentialFields(): array;

    /**
     * @return array<int, string>
     */
    public static function setupSteps(): array;

    public static function hasWebhook(): bool;

    public static function webhookRouteParam(): ?string;

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function testConnection(array $credentials): bool;
}
