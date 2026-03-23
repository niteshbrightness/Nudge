<?php

namespace App\Contracts\Repositories;

use App\Models\Integration;
use Illuminate\Support\Collection;

interface IntegrationRepositoryInterface
{
    public function findByService(string $service): ?Integration;

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $meta
     */
    public function upsert(string $service, array $credentials, array $meta = []): Integration;

    public function delete(Integration $integration): void;

    /**
     * @return Collection<int, Integration>
     */
    public function allForTenant(): Collection;
}
