<?php

declare(strict_types=1);

namespace PHPLift\Console;

use PHPLift\Engine\MigrationPlan;
use PHPLift\Scanner\ProjectScanner;
use PHPLift\Template\TemplateMigrator;
use PHPLift\Transformer\CodeTransformer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

final class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migrate')
            ->setDescription('Transform code + syntax check + generate migration report')
            ->addArgument('path', InputArgument::REQUIRED, 'Project path')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Target version', '8.0')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Report output file (json)', 'phplift-report.json')
            ->addOption('write', 'w', InputOption::VALUE_NONE, 'Actually write transformed files (default is dry-run)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $target = $input->getOption('target');
        $reportFile = $input->getOption('output');
        $dryRun = !$input->getOption('write');

        if (!is_dir($path)) {
            $output->writeln("<error>❌ Directory not found: {$path}</error>");
            return Command::FAILURE;
        }

        // 1. 扫描
        $output->writeln("🔍 Scanning...");
        $scanner = new ProjectScanner();
        $profile = $scanner->scan($path);

        if ($profile->framework->name === 'unknown') {
            $output->writeln("<error>❌ Cannot detect framework version</error>");
            return Command::FAILURE;
        }

        $output->writeln("📋 Source: {$profile->framework->name} {$profile->framework->version}");
        $output->writeln("📋 Target: {$target}");
        if ($dryRun) {
            $output->writeln("<comment>📋 Mode: dry-run (use --write to apply changes)</comment>");
        }

        // 2. 计算路径
        $steps = MigrationPlan::compute($profile->framework->version, $target);
        if ($steps === []) {
            $output->writeln("<info>✅ Already at target version</info>");
            return Command::SUCCESS;
        }

        $allRules = [];
        foreach ($steps as $step) {
            $output->writeln("  → {$step->label()} (" . count($step->rules) . " rules)");
            $allRules = array_merge($allRules, $step->rules);
        }

        $output->writeln("📋 Rules: " . count($allRules));
        $output->writeln("");

        // 3. 转换 + 语法检查
        $output->writeln("⚡ Transforming and checking...");
        $transformer = new CodeTransformer();

        try {
            $finder = new Finder();
            $finder->files()->in($path)->name('*.php')->notPath(['vendor', 'node_modules', 'runtime', 'Runtime']);
        } catch (\Throwable $e) {
            $output->writeln("<error>❌ {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $report = [
            'source' => "{$profile->framework->name} {$profile->framework->version}",
            'target' => $target,
            'rules' => count($allRules),
            'files_scanned' => 0,
            'files_changed' => 0,
            'syntax_ok' => 0,
            'syntax_errors' => [],
            'changed_files' => [],
            'manual_review' => [],
        ];

        foreach ($finder as $file) {
            $report['files_scanned']++;
            $result = $transformer->transformFile($file->getRealPath(), $allRules, $dryRun);

            if (!$result->changed) {
                continue;
            }

            $report['files_changed']++;
            $relativePath = $file->getRelativePathname();
            $report['changed_files'][] = $relativePath;

            // 语法检查
            $tmp = tempnam(sys_get_temp_dir(), 'phplift_lint_');
            if ($tmp === false) {
                $report['syntax_errors'][] = [
                    'file' => $relativePath,
                    'error' => 'Failed to create temp file for syntax check',
                ];
                continue;
            }
            file_put_contents($tmp, $result->newCode);
            exec("php -l {$tmp} 2>&1", $lintOutput, $lintCode);
            @unlink($tmp);

            if ($lintCode === 0) {
                $report['syntax_ok']++;
            } else {
                $report['syntax_errors'][] = [
                    'file' => $relativePath,
                    'error' => implode(' ', $lintOutput),
                ];
            }

            // 检测需要手动处理的模式
            $manualPatterns = [];
            if (str_contains($result->newCode, '$this->success(') || str_contains($result->newCode, '$this->error(')) {
                $manualPatterns[] = '$this->success/error() needs manual replacement';
            }
            if (str_contains($result->newCode, '$this->ajaxReturn(')) {
                $manualPatterns[] = '$this->ajaxReturn() needs manual replacement';
            }
            if (str_contains($result->newCode, '$request->') && !str_contains($result->originalCode, '$request')) {
                $manualPatterns[] = '$request variable needs injection (add Request $request parameter)';
            }
            if ($manualPatterns) {
                $report['manual_review'][] = [
                    'file' => $relativePath,
                    'issues' => $manualPatterns,
                ];
            }
        }

        // 3.5 模板文件迁移
        $templateMigrator = new TemplateMigrator();
        $tplFinder = new Finder();
        try {
            $tplFinder->files()->in($path)->name(['*.html', '*.tpl'])->notPath(['vendor', 'node_modules', 'runtime']);
            $report['templates_scanned'] = 0;
            $report['templates_changed'] = 0;

            foreach ($tplFinder as $tplFile) {
                $report['templates_scanned']++;
                $tplResult = $templateMigrator->migrateFile($tplFile->getRealPath(), $dryRun);
                if ($tplResult->changed) {
                    $report['templates_changed']++;
                    $report['changed_files'][] = $tplFile->getRelativePathname() . ' (template)';
                }
            }
        } catch (\Throwable) {
            // No template files found — not an error
            $report['templates_scanned'] = 0;
            $report['templates_changed'] = 0;
        }

        // 4. 输出结果
        $output->writeln("");
        $output->writeln("═══════════════════════════════════════");
        $output->writeln("  PHPLift Migration Report");
        $output->writeln("═══════════════════════════════════════");
        $output->writeln("  Files scanned:     {$report['files_scanned']}");
        $output->writeln("  Files changed:     {$report['files_changed']}");
        $output->writeln("  Syntax OK:         {$report['syntax_ok']}");
        $output->writeln("  Syntax errors:     " . count($report['syntax_errors']));
        $output->writeln("  Need manual review:" . count($report['manual_review']));
        $output->writeln("  Templates changed: {$report['templates_changed']}/{$report['templates_scanned']}");
        $output->writeln("───────────────────────────────────────");

        if ($report['syntax_errors']) {
            $output->writeln("");
            $output->writeln("<error>Syntax errors:</error>");
            foreach ($report['syntax_errors'] as $err) {
                $output->writeln("  ❌ {$err['file']}");
            }
        }

        if ($report['manual_review']) {
            $output->writeln("");
            $output->writeln("<comment>Need manual review:</comment>");
            foreach (array_slice($report['manual_review'], 0, 10) as $item) {
                $output->writeln("  ⚠️  {$item['file']}");
                foreach ($item['issues'] as $issue) {
                    $output->writeln("      → {$issue}");
                }
            }
            if (count($report['manual_review']) > 10) {
                $output->writeln("  ... and " . (count($report['manual_review']) - 10) . " more");
            }
        }

        // 5. 保存 JSON 报告
        $written = @file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($written === false) {
            $output->writeln("<error>⚠️  Failed to write report to: {$reportFile}</error>");
        } else {
            $output->writeln("📄 Report saved to: {$reportFile}");
        }

        return $report['syntax_errors'] ? Command::FAILURE : Command::SUCCESS;
    }
}
