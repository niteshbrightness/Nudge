<?php

namespace App\ProjectSync\Sources;

use App\Contracts\ProjectSync\ProjectSourceInterface;
use App\ProjectSync\NormalizedProject;
use App\Services\ActiveCollabService;

class ActiveCollabSource implements ProjectSourceInterface
{
    public function __construct(private readonly ActiveCollabService $activeCollabService) {}

    public function source(): string
    {
        return 'activecollab';
    }

    public function isAvailable(): bool
    {
        return $this->activeCollabService->isConfigured();
    }

    /**
     * @return array<int, NormalizedProject>
     */
    public function fetchProjects(): array
    {
        return array_map(
            fn (array $item) => new NormalizedProject(
                source: 'activecollab',
                externalId: (string) $item['id'],
                name: $item['name'],
                description: $item['body'] ?? null,
                status: ($item['is_completed'] ?? false) ? 'completed' : 'active',
                url: $item['url'] ?? null,
            ),
            $this->activeCollabService->fetchProjects(),
        );
    }
}
