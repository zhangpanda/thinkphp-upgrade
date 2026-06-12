<?php

declare(strict_types=1);

namespace PHPLift\AI;

final readonly class MigrationContext
{
    public function __construct(
        public string $originalCode,
        public string $filePath,
        public string $fromFramework,
        public string $toFramework,
        public ?string $errorMessage = null,
    ) {}
}
