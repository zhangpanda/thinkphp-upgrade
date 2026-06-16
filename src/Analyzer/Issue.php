<?php

declare(strict_types=1);

namespace ThinkUpgrade\Analyzer;

final readonly class Issue
{
    public function __construct(
        public string $ruleName,
        public string $description,
        public int $line,
        public string $originalCode,
        public ?string $suggestedCode = null,
        public bool $autoFixable = true,
    ) {}
}
