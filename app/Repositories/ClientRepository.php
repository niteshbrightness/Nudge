<?php

namespace App\Repositories;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

    public function syncProjects(Client $client, array $projectIds): void
    {
        DB::transaction(function () use ($client, $projectIds): void {
            Project::query()
                ->where('client_id', $client->id)
                ->whereNotIn('id', $projectIds)
                ->update(['client_id' => null]);

            if (! empty($projectIds)) {
                Project::query()
                    ->whereIn('id', $projectIds)
                    ->where(fn ($q) => $q->whereNull('client_id')->orWhere('client_id', $client->id))
                    ->update(['client_id' => $client->id]);
            }
        });
    }
}
