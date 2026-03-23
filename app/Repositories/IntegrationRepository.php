<?php

namespace App\Repositories;

use App\Contracts\Repositories\IntegrationRepositoryInterface;
use App\Models\Integration;
use Illuminate\Support\Collection;

class IntegrationRepository implements IntegrationRepositoryInterface
{
    public function findByService(string $service): ?Integration
    {
        return Integration::query()->where('service', $service)->first();
    }

    public function upsert(string $service, array $credentials, array $meta = []): Integration
    {
        $integration = Integration::query()->where('service', $service)->first();

        if ($integration) {
            $existingMeta = $integration->meta ?? [];
            $integration->update([
                'credentials' => $credentials,
                'meta' => array_merge($existingMeta, $meta),
                'is_active' => true,
            ]);

            return $integration->fresh();
        }

        return Integration::create([
            'service' => $service,
            'credentials' => $credentials,
            'meta' => $meta,
            'is_active' => true,
        ]);
    }

    public function delete(Integration $integration): void
    {
        $integration->delete();
    }

    public function allForTenant(): Collection
    {
        return Integration::query()->get();
    }
}
