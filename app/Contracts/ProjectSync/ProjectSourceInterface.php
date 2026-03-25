<?php

namespace App\Contracts\ProjectSync;

use App\ProjectSync\NormalizedProject;

interface ProjectSourceInterface
{
    /**
     * The service key — must match IntegrationInterface::service().
     */
    public function source(): string;

    /**
     * Whether this source is currently configured and active.
     */
    public function isAvailable(): bool;

    /**
     * Fetch and normalize all projects from this source.
     *
     * @return array<int, NormalizedProject>
     */
    public function fetchProjects(): array;
}
