<?php

declare(strict_types=1);

namespace PHPLift\Scanner;

final readonly class FrameworkInfo
{
    public function __construct(
        public string $name,
        public string $version,
        public int $confidence,
    ) {}
}
