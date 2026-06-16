<?php

declare(strict_types=1);

namespace ThinkUpgrade\Console;

use ThinkUpgrade\Engine\MigrationPlan;
use ThinkUpgrade\Scanner\ProjectScanner;
use ThinkUpgrade\Transformer\CodeTransformer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

final class TransformCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('transform')
            ->setDescription('Transform PHP code to target framework version')
            ->addArgument('path', InputArgument::REQUIRED, 'Project path')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Target version (e.g. 6.0 or 8.0)', '6.0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $dryRun = $input->getOption('dry-run');
        $target = $input->getOption('target');

        if (!is_dir($path)) {
            $output->writeln("<error>❌ Directory not found: {$path}</error>");
            return Command::FAILURE;
        }

        $output->writeln($dryRun ? '🔍 Dry run mode' : '⚡ Transforming...');

        $scanner = new ProjectScanner();
        $profile = $scanner->scan($path);

        if ($profile->framework->name === 'unknown') {
            $output->writeln('<error>❌ 无法识别框架版本，请确认项目路径正确</error>');
            return Command::FAILURE;
        }

        $output->writeln("📋 Source: {$profile->framework->name} {$profile->framework->version}");
        $output->writeln("📋 Target: ThinkPHP {$target}");

        $steps = MigrationPlan::compute($profile->framework->version, $target);

        if ($steps === []) {
            $output->writeln('<info>✅ 无需迁移：已是目标版本或不支持该路径</info>');
            return Command::SUCCESS;
        }

        $pathLabels = [$steps[0]->from];
        foreach ($steps as $s) {
            $pathLabels[] = $s->to;
        }
        $output->writeln("📋 Path: " . implode(' → ', $pathLabels));
        $output->writeln("📋 Steps: " . count($steps) . ", Rules: " . array_sum(array_map(fn($s) => count($s->rules), $steps)));

        $allRules = [];
        foreach ($steps as $step) {
            $allRules = array_merge($allRules, $step->rules);
        }

        $transformer = new CodeTransformer();

        try {
            $finder = new Finder();
            $finder->files()->in($path)->name('*.php')->notPath(['vendor', 'node_modules']);
        } catch (\Symfony\Component\Finder\Exception\DirectoryNotFoundException $e) {
            $output->writeln("<error>❌ Cannot scan directory: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $changed = 0;
        foreach ($finder as $file) {
            $result = $transformer->transformFile($file->getRealPath(), $allRules, $dryRun);
            if ($result->changed) {
                $changed++;
                $output->writeln("  ✏️  {$file->getRelativePathname()}");
            }
        }

        $output->writeln("\n✅ {$changed} files " . ($dryRun ? 'would be changed' : 'changed'));
        return Command::SUCCESS;
    }
}
