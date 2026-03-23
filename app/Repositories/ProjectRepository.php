<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Project::query()
            ->with(['client'])
            ->latest()
            ->paginate($perPage);
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
