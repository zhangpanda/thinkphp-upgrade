<?php

declare(strict_types=1);

namespace PHPLift\Reporter;

use PHPLift\Analyzer\FileAnalysis;

final class ConsoleReporter implements ReporterInterface
{
    public function generate(array $analyses): string
    {
        $totalIssues = 0;
        $autoFixable = 0;
        $byRule = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->issues as $issue) {
                $totalIssues++;
                if ($issue->autoFixable) {
                    $autoFixable++;
                }
                $byRule[$issue->ruleName] = ($byRule[$issue->ruleName] ?? 0) + 1;
            }
        }

        $lines = [
            '═══════════════════════════════════════',
            ' ThinkPHP-Upgrade Migration Report',
            '═══════════════════════════════════════',
            " Files analyzed: " . count($analyses),
            " 🟢 Auto-fixable:  {$autoFixable}",
            " 🔴 Manual/AI:     " . ($totalIssues - $autoFixable),
            " Total issues:     {$totalIssues}",
            '───────────────────────────────────────',
        ];

        arsort($byRule);
        foreach ($byRule as $rule => $count) {
            $lines[] = "  {$rule}: {$count}";
        }

        $lines[] = '═══════════════════════════════════════';
        return implode("\n", $lines);
    }
}
