<?php

declare(strict_types=1);

namespace PHPLift\Reporter;

use PHPLift\Analyzer\FileAnalysis;

interface ReporterInterface
{
    /** @param list<FileAnalysis> $analyses */
    public function generate(array $analyses): string;
}
