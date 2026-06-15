<?php

declare(strict_types=1);

namespace PHPLift\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ServeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('serve')
            ->setDescription('Start the interactive Web UI for reviewing migrations')
            ->addArgument('path', InputArgument::REQUIRED, 'Project path to migrate')
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Port number', '8190')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Target version', '8.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = realpath($input->getArgument('path'));
        $port = $input->getOption('port');
        $target = $input->getOption('target');

        if (!$path || !is_dir($path)) {
            $output->writeln("<error>❌ Directory not found: {$input->getArgument('path')}</error>");
            return Command::FAILURE;
        }

        $router = __DIR__ . '/../../web/server.php';
        if (!file_exists($router)) {
            $output->writeln("<error>❌ Web server router not found</error>");
            return Command::FAILURE;
        }

        $output->writeln("🚀 PHPLift Web UI");
        $output->writeln("   Project: {$path}");
        $output->writeln("   Target:  {$target}");
        $output->writeln("   URL:     <info>http://localhost:{$port}</info>");
        $output->writeln("");
        $output->writeln("   Press Ctrl+C to stop.");
        $output->writeln("");

        $env = "PHPLIFT_PROJECT={$path} PHPLIFT_TARGET={$target}";
        $docroot = dirname($router);
        passthru("{$env} php -S 0.0.0.0:{$port} -t {$docroot} {$router}");

        return Command::SUCCESS;
    }
}
