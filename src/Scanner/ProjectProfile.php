<?php

declare(strict_types=1);

namespace ThinkUpgrade\Scanner;

final readonly class ProjectProfile
{
    public function __construct(
        public string $path,
        public string $phpVersion,
        public FrameworkInfo $framework,
        public int $phpFiles,
        public int $totalLines,
    ) {}
}
