<?php

declare(strict_types=1);

namespace ThinkUpgrade\Template;

final readonly class TemplateResult
{
    public function __construct(
        public string $original,
        public string $transformed,
        public array $appliedRules = [],
        public bool $changed = false,
    ) {}
}
