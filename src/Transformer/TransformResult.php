<?php

declare(strict_types=1);

namespace PHPLift\Transformer;

final readonly class TransformResult
{
    public function __construct(
        public string $filePath,
        public string $originalCode,
        public string $newCode,
        public array $appliedRules = [],
        public bool $changed = false,
    ) {}
}
