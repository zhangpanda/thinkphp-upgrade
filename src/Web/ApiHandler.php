<?php

declare(strict_types=1);

namespace PHPLift\Web;

use PHPLift\Engine\MigrationPlan;
use PHPLift\Scanner\ProjectScanner;
use PHPLift\Transformer\CodeTransformer;
use Symfony\Component\Finder\Finder;

final class ApiHandler
{
    private string $stateFile;

    public function __construct(
        private readonly string $projectPath,
        private readonly string $targetVersion,
    ) {
        $this->stateFile = sys_get_temp_dir() . '/phplift_' . md5($this->projectPath) . '.json';
    }

    public function handle(string $uri, string $method): string
    {
        return match (true) {
            $uri === '/api/project' && $method === 'GET' => $this->getProject(),
            $uri === '/api/changes' && $method === 'GET' => $this->getChanges(),
            $uri === '/api/changes/confirm' && $method === 'POST' => $this->confirmChange(),
            $uri === '/api/changes/skip' && $method === 'POST' => $this->skipChange(),
            $uri === '/api/changes/confirm-all' && $method === 'POST' => $this->confirmAll(),
            default => json_encode(['error' => 'Not found'], JSON_THROW_ON_ERROR),
        };
    }

    private function getProject(): string
    {
        $scanner = new ProjectScanner();
        $profile = $scanner->scan($this->projectPath);

        $state = $this->getState();
        $totalChanges = count($state['changes'] ?? []);
        $confirmed = count(array_filter($state['changes'] ?? [], fn($c) => $c['status'] === 'confirmed'));
        $skipped = count(array_filter($state['changes'] ?? [], fn($c) => $c['status'] === 'skipped'));

        return json_encode([
            'path' => $this->projectPath,
            'framework' => $profile->framework->name . ' ' . $profile->framework->version,
            'target' => $this->targetVersion,
            'totalFiles' => $profile->phpFiles,
            'totalChanges' => $totalChanges,
            'confirmed' => $confirmed,
            'skipped' => $skipped,
            'pending' => $totalChanges - $confirmed - $skipped,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function getChanges(): string
    {
        $state = $this->getOrComputeState();
        $filter = $_GET['status'] ?? 'pending';

        $changes = array_values(array_filter(
            $state['changes'],
            fn($c) => $filter === 'all' || $c['status'] === $filter
        ));

        return json_encode([
            'total' => count($state['changes']),
            'filtered' => count($changes),
            'changes' => array_slice($changes, 0, 50), // paginate
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function confirmChange(): string
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $id = $body['id'] ?? null;
        if ($id === null) {
            return json_encode(['error' => 'Missing id']);
        }

        $state = $this->getState();
        foreach ($state['changes'] as &$change) {
            if ($change['id'] === $id) {
                $change['status'] = 'confirmed';
                break;
            }
        }
        $this->saveState($state);

        return json_encode(['ok' => true]);
    }

    private function skipChange(): string
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $id = $body['id'] ?? null;
        if ($id === null) {
            return json_encode(['error' => 'Missing id']);
        }

        $state = $this->getState();
        foreach ($state['changes'] as &$change) {
            if ($change['id'] === $id) {
                $change['status'] = 'skipped';
                break;
            }
        }
        $this->saveState($state);

        return json_encode(['ok' => true]);
    }

    private function confirmAll(): string
    {
        $state = $this->getState();
        foreach ($state['changes'] as &$change) {
            if ($change['status'] === 'pending') {
                $change['status'] = 'confirmed';
            }
        }
        $this->saveState($state);

        // Apply confirmed changes
        $applied = 0;
        foreach ($state['changes'] as $change) {
            if ($change['status'] === 'confirmed' && !empty($change['newCode'])) {
                $filePath = $change['filePath'];
                @file_put_contents($filePath . '.bak', $change['originalCode']);
                @file_put_contents($filePath, $change['newCode']);
                $applied++;
            }
        }

        return json_encode(['ok' => true, 'applied' => $applied]);
    }

    private function getOrComputeState(): array
    {
        $state = $this->getState();
        if (!empty($state['changes'])) {
            return $state;
        }

        // Compute changes
        $scanner = new ProjectScanner();
        $profile = $scanner->scan($this->projectPath);
        $steps = MigrationPlan::compute($profile->framework->version, $this->targetVersion);

        $allRules = [];
        foreach ($steps as $step) {
            $allRules = array_merge($allRules, $step->rules);
        }

        $transformer = new CodeTransformer();
        $finder = new Finder();
        $finder->files()->in($this->projectPath)->name('*.php')
            ->notPath(['vendor', 'node_modules', 'runtime', 'Runtime']);

        $changes = [];
        $id = 0;
        foreach ($finder as $file) {
            $result = $transformer->transformFile($file->getRealPath(), $allRules, dryRun: true);
            if ($result->changed) {
                $changes[] = [
                    'id' => ++$id,
                    'filePath' => $result->filePath,
                    'relativePath' => $file->getRelativePathname(),
                    'rules' => $result->appliedRules,
                    'originalCode' => $result->originalCode,
                    'newCode' => $result->newCode,
                    'status' => 'pending',
                ];
            }
        }

        $state = ['changes' => $changes];
        $this->saveState($state);

        return $state;
    }

    private function getState(): array
    {
        if (!file_exists($this->stateFile)) {
            return ['changes' => []];
        }
        return json_decode(file_get_contents($this->stateFile), true) ?: ['changes' => []];
    }

    private function saveState(array $state): void
    {
        file_put_contents($this->stateFile, json_encode($state, JSON_UNESCAPED_UNICODE));
    }
}
