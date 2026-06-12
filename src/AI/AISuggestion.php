<?php

declare(strict_types=1);

namespace PHPLift\AI;

final readonly class AISuggestion
{
    public function __construct(
        public string $suggestedCode,
        public string $explanation,
        public float $confidence,
        public bool $needsReview,
    ) {}
}
