<?php

namespace App\Contracts\Repositories;

use App\Models\WebhookEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface WebhookEventRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    public function find(int $id): WebhookEvent;

    public function store(array $data): WebhookEvent;

    /** @return Collection<int, WebhookEvent> */
    public function recentForProject(int $projectId, int $limit = 10): Collection;
}
