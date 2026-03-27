<?php

namespace App\Repositories;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository implements ClientRepositoryInterface
{
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return Client::query()
            ->with('timezone')
            ->withCount('projects')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->when($filters['project_id'] ?? null, fn ($q, $projectId) => $q->whereHas('projects', fn ($q) => $q->where('id', $projectId)))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Client
    {
        return Client::query()->with(['timezone', 'projects'])->findOrFail($id);
    }

    public function create(array $data): Client
    {
        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client->fresh(['timezone', 'projects']);
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }

    public function syncProjects(Client $client, array $projectIds): void
    {
        $client->projects()->sync($projectIds);
    }
}
