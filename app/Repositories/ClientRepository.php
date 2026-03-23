<?php

namespace App\Repositories;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository implements ClientRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Client::query()
            ->with('timezone')
            ->withCount('projects')
            ->latest()
            ->paginate($perPage);
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
}
