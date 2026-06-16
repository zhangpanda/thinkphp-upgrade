<?php

declare(strict_types=1);

namespace ThinkUpgrade\Analyzer;

final readonly class FileAnalysis
{
    /** @param list<Issue> $issues */
    public function __construct(
        public string $filePath,
        public array $issues = [],
    ) {}

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    public function autoFixableCount(): int
    {
        return count(array_filter($this->issues, fn(Issue $i) => $i->autoFixable));
    }
}
