<?php

declare(strict_types=1);

namespace PHPLift\Reporter;

use PHPLift\Analyzer\FileAnalysis;

final class JsonReporter implements ReporterInterface
{
    public function generate(array $analyses): string
    {
        $report = [
            'summary' => [
                'files' => count($analyses),
                'total_issues' => 0,
                'auto_fixable' => 0,
            ],
            'files' => [],
        ];

        foreach ($analyses as $analysis) {
            $fileReport = [
                'path' => $analysis->filePath,
                'issues' => [],
            ];

            foreach ($analysis->issues as $issue) {
                $report['summary']['total_issues']++;
                if ($issue->autoFixable) {
                    $report['summary']['auto_fixable']++;
                }
                $fileReport['issues'][] = [
                    'rule' => $issue->ruleName,
                    'line' => $issue->line,
                    'description' => $issue->description,
                    'auto_fixable' => $issue->autoFixable,
                ];
            }

            if ($fileReport['issues'] !== []) {
                $report['files'][] = $fileReport;
            }
        }

        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
