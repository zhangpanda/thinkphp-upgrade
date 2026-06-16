<?php

declare(strict_types=1);

namespace ThinkUpgrade\AI;

interface AIProviderInterface
{
    public function suggest(MigrationContext $context): AISuggestion;
    public function isAvailable(): bool;
}
