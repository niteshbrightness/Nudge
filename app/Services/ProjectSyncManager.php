<?php

namespace App\Services;

use App\Contracts\ProjectSync\ProjectSourceInterface;
use InvalidArgumentException;

class ProjectSyncManager
{
    /** @var array<string, ProjectSourceInterface> */
    private array $sources = [];

    public function register(ProjectSourceInterface $source): void
    {
        $this->sources[$source->source()] = $source;
    }

    /**
     * @return array<string, ProjectSourceInterface>
     */
    public function all(): array
    {
        return $this->sources;
    }

    public function get(string $source): ProjectSourceInterface
    {
        if (! isset($this->sources[$source])) {
            throw new InvalidArgumentException("Project source [{$source}] is not registered.");
        }

        return $this->sources[$source];
    }
}
