<?php

namespace App\Contracts\Repositories;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): Project;

    public function findByActivecollabId(int $activecollabId): ?Project;

    public function upsertFromActiveCollab(array $data): Project;

    /** @return Collection<int, Project> */
    public function allForTenant(): Collection;
}
