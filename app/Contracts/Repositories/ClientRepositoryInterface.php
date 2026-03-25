<?php

namespace App\Contracts\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClientRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    public function find(int $id): Client;

    public function create(array $data): Client;

    public function update(Client $client, array $data): Client;

    public function delete(Client $client): void;

    /** @param array<int> $projectIds */
    public function syncProjects(Client $client, array $projectIds): void;
}
