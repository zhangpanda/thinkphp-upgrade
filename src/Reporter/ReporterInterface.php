<?php

declare(strict_types=1);

namespace ThinkUpgrade\Reporter;

use ThinkUpgrade\Analyzer\FileAnalysis;

interface ReporterInterface
{
    /** @param list<FileAnalysis> $analyses */
    public function generate(array $analyses): string;
}
