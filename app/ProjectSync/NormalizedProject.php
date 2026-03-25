<?php

namespace App\ProjectSync;

readonly class NormalizedProject
{
    public function __construct(
        public string $source,
        public string $externalId,
        public string $name,
        public ?string $description,
        public string $status,
        public ?string $url,
    ) {}
}
