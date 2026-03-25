<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return Project::query()
            ->with(['client'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Project
    {
        return Project::query()->with(['client', 'webhookEvents' => fn ($q) => $q->latest()->limit(10)])->findOrFail($id);
    }

    public function findByActivecollabId(int $activecollabId): ?Project
    {
        return Project::query()->where('activecollab_id', $activecollabId)->first();
    }

    public function upsertFromActiveCollab(array $data): Project
    {
        return Project::updateOrCreate(
            ['activecollab_id' => $data['activecollab_id']],
            $data,
        );
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->refresh()->load('client');
    }

    public function allForTenant(): Collection
    {
        return Project::query()->with('client')->latest()->get();
    }
}
