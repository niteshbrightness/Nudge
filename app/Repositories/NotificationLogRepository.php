<?php

namespace App\Repositories;

use App\Contracts\Repositories\NotificationLogRepositoryInterface;
use App\Models\NotificationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationLogRepository implements NotificationLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return NotificationLog::query()
            ->with(['client', 'project'])
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->whereHas(
                    'client',
                    fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                )
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== 'all',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                $filters['project_id'] ?? null,
                fn ($q, $projectId) => $q->where('project_id', $projectId)
            )
            ->latest('sent_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
