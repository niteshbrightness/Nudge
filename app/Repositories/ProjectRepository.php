<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use App\ProjectSync\NormalizedProject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $allowedSortColumns = ['name', 'status', 'external_id', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSortColumns) ? $filters['sort_by'] : null;
        $sortDir = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Project::query()
            ->with(['clients'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($q, $clientId) => $q->whereHas('clients', fn ($q) => $q->where('clients.id', $clientId)))
            ->when($sortBy, fn ($q) => $q->orderBy($sortBy, $sortDir), fn ($q) => $q->latest())
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Project
    {
        return Project::query()->with(['clients', 'webhookEvents' => fn ($q) => $q->latest()->limit(10)])->findOrFail($id);
    }

    public function findByExternalId(string $source, string $externalId): ?Project
    {
        return Project::query()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->first();
    }

    public function upsertFromSource(NormalizedProject $project): Project
    {
        return Project::updateOrCreate(
            ['source' => $project->source, 'external_id' => $project->externalId],
            [
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'url' => $project->url,
            ]
        );
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->refresh()->load('clients');
    }

    public function allForTenant(): Collection
    {
        return Project::query()->with('clients')->latest()->get();
    }
}
