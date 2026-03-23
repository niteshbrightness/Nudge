<?php

namespace App\Services;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use InvalidArgumentException;

class IntegrationManager
{
    /** @var array<string, class-string<IntegrationInterface>> */
    private array $registry = [];

    /**
     * @param  class-string<IntegrationInterface>  $integrationClass
     */
    public function register(string $integrationClass): void
    {
        $this->registry[$integrationClass::service()] = $integrationClass;
    }

    /**
     * @return array<string, class-string<IntegrationInterface>>
     */
    public function all(): array
    {
        return $this->registry;
    }

    /**
     * @return class-string<IntegrationInterface>
     */
    public function get(string $service): string
    {
        if (! isset($this->registry[$service])) {
            throw new InvalidArgumentException("Integration [{$service}] is not registered.");
        }

        return $this->registry[$service];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function credentials(string $service): ?array
    {
        $integration = Integration::query()
            ->where('service', $service)
            ->where('is_active', true)
            ->first();

        return $integration?->credentials;
    }

    public function isConnected(string $service): bool
    {
        return Integration::query()
            ->where('service', $service)
            ->where('is_active', true)
            ->exists();
    }
}
