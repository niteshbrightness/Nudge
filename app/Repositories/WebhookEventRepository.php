<?php

namespace App\Repositories;

use App\Contracts\Repositories\WebhookEventRepositoryInterface;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class WebhookEventRepository implements WebhookEventRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return WebhookEvent::query()
            ->with('project')
            ->latest('received_at')
            ->paginate($perPage);
    }

    public function find(int $id): WebhookEvent
    {
        return WebhookEvent::query()->with('project')->findOrFail($id);
    }

    public function store(array $data): WebhookEvent
    {
        return WebhookEvent::create($data);
    }

    public function recentForProject(int $projectId, int $limit = 10): Collection
    {
        return WebhookEvent::query()
            ->where('project_id', $projectId)
            ->latest('received_at')
            ->limit($limit)
            ->get();
    }
}
