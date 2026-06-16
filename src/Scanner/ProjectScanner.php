<?php

declare(strict_types=1);

namespace PHPLift\Scanner;

use Symfony\Component\Finder\Finder;

final class ProjectScanner
{
    public function scan(string $projectPath): ProjectProfile
    {
        if (!is_dir($projectPath)) {
            return new ProjectProfile(
                path: $projectPath,
                phpVersion: PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                framework: new FrameworkInfo('unknown', '0.0', 0),
                phpFiles: 0,
                totalLines: 0,
            );
        }

        $framework = $this->detectFramework($projectPath);
        $phpVersion = $this->detectPhpVersion($projectPath);
        [$phpFiles, $totalLines] = $this->countFiles($projectPath);

        return new ProjectProfile(
            path: realpath($projectPath) ?: $projectPath,
            phpVersion: $phpVersion,
            framework: $framework,
            phpFiles: $phpFiles,
            totalLines: $totalLines,
        );
    }

    private function detectFramework(string $path): FrameworkInfo
    {
        $composerFile = $path . '/composer.json';
        if (file_exists($composerFile)) {
            $content = file_get_contents($composerFile);
            if ($content === false) {
                return new FrameworkInfo('unknown', '0.0', 0);
            }
            $composer = json_decode($content, true);
            if (!is_array($composer)) {
                return new FrameworkInfo('unknown', '0.0', 0);
            }
            $version = $composer['require']['topthink/framework'] ?? null;
            if ($version) {
                $cleaned = preg_replace('/[^0-9.]/', '', $version);
                if ($cleaned === null) {
                    return new FrameworkInfo('unknown', '0.0', 0);
                }
                $cleaned = trim($cleaned, '.');
                return new FrameworkInfo('thinkphp', $cleaned, 95);
            }
        }

        // TP3.x signature: ThinkPHP/ThinkPHP.php
        $tp3File = $path . '/ThinkPHP/ThinkPHP.php';
        if (file_exists($tp3File)) {
            $content = file_get_contents($tp3File);
            if ($content !== false && preg_match("/THINK_VERSION.*?'(3\.\d+\.\d+)'/", $content, $m)) {
                return new FrameworkInfo('thinkphp', $m[1], 90);
            }
            return new FrameworkInfo('thinkphp', '3.2', 70);
        }

        return new FrameworkInfo('unknown', '0.0', 0);
    }

    private function detectPhpVersion(string $path): string
    {
        $composerFile = $path . '/composer.json';
        if (file_exists($composerFile)) {
            $content = file_get_contents($composerFile);
            if ($content !== false) {
                $composer = json_decode($content, true);
                if (is_array($composer)) {
                    $php = $composer['require']['php'] ?? null;
                    if ($php && preg_match('/(\d+\.\d+)/', $php, $m)) {
                        return $m[1];
                    }
                }
            }
        }
        return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }

    /** @return array{int, int} [fileCount, lineCount] */
    private function countFiles(string $path): array
    {
        try {
            $finder = new Finder();
            $finder->files()->in($path)->name('*.php')->notPath(['vendor', 'node_modules']);
        } catch (\Symfony\Component\Finder\Exception\DirectoryNotFoundException) {
            return [0, 0];
        }

        $files = 0;
        $lines = 0;
        foreach ($finder as $file) {
            $files++;
            $lines += substr_count($file->getContents(), "\n") + 1;
        }

        return [$files, $lines];
    }
}
