<?php

declare(strict_types=1);

namespace PHPLift\Console;

use PHPLift\Analyzer\CodeAnalyzer;
use PHPLift\RuleSet\ThinkPHP\ConfigCallRule;
use PHPLift\RuleSet\ThinkPHP\InputCallRule;
use PHPLift\RuleSet\ThinkPHP\IsPostRule;
use PHPLift\RuleSet\ThinkPHP\ModelCallRule;
use PHPLift\RuleSet\ThinkPHP\UrlGenerateRule;
use PHPLift\Scanner\ProjectScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

final class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription('Analyze a PHP project for migration opportunities')
            ->addArgument('path', InputArgument::REQUIRED, 'Project path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');

        if (!is_dir($path)) {
            $output->writeln("<error>❌ Directory not found: {$path}</error>");
            return Command::FAILURE;
        }

        $output->writeln("🔍 Scanning: {$path}");

        $scanner = new ProjectScanner();
        $profile = $scanner->scan($path);

        $output->writeln("📋 Framework: {$profile->framework->name} {$profile->framework->version} (confidence: {$profile->framework->confidence}%)");
        $output->writeln("📊 PHP files: {$profile->phpFiles}, Lines: {$profile->totalLines}");

        $analyzer = new CodeAnalyzer();
        $analyzer->setRules([
            new ModelCallRule(),
            new ConfigCallRule(),
            new InputCallRule(),
            new UrlGenerateRule(),
            new IsPostRule(),
        ]);

        try {
            $finder = new Finder();
            $finder->files()->in($path)->name('*.php')->notPath(['vendor', 'node_modules']);
        } catch (\Symfony\Component\Finder\Exception\DirectoryNotFoundException $e) {
            $output->writeln("<error>❌ Cannot scan directory: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $totalIssues = 0;
        foreach ($finder as $file) {
            $analysis = $analyzer->analyzeFile($file->getRealPath());
            if ($analysis->hasIssues()) {
                $totalIssues += count($analysis->issues);
                $output->writeln("  {$file->getRelativePathname()}: " . count($analysis->issues) . " issues");
            }
        }

        $output->writeln("\n✅ Total issues found: {$totalIssues}");
        return Command::SUCCESS;
    }
}
