<?php

declare(strict_types=1);

namespace PHPLift\Engine;

use PHPLift\RuleSet\RuleInterface;

final readonly class MigrationStep
{
    /** @param list<RuleInterface> $rules */
    public function __construct(
        public string $from,
        public string $to,
        public array $rules,
    ) {}

    public function label(): string
    {
        return "{$this->from} → {$this->to}";
    }
}
