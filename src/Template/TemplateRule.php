<?php

declare(strict_types=1);

namespace ThinkUpgrade\Template;

final readonly class TemplateRule
{
    public function __construct(
        public string $name,
        public string $pattern,
        public string $replacement,
    ) {}
}
