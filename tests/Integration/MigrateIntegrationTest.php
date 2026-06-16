<?php

declare(strict_types=1);

namespace ThinkUpgrade\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ThinkUpgrade\Engine\MigrationPlan;
use ThinkUpgrade\Scanner\ProjectScanner;
use ThinkUpgrade\Template\TemplateMigrator;
use ThinkUpgrade\Transformer\CodeTransformer;
use Symfony\Component\Finder\Finder;

/**
 * Full migration integration test using real TP3.2 fixture project.
 */
final class MigrateIntegrationTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = __DIR__ . '/../Fixtures/real-tp32-project';
    }

    public function testScannerDetectsThinkPHP32(): void
    {
        $scanner = new ProjectScanner();
        $profile = $scanner->scan($this->fixturePath);

        $this->assertSame('thinkphp', $profile->framework->name);
        $this->assertStringStartsWith('3', $profile->framework->version);
    }

    public function testMigrationPlanComputesCorrectSteps(): void
    {
        $steps = MigrationPlan::compute('3.2', '8.0');

        $this->assertCount(3, $steps); // 3.2→5.1, 5.1→6.0, 6.0→8.0
        $this->assertSame('thinkphp:3.2 → thinkphp:5.1', $steps[0]->label());
        $this->assertSame('thinkphp:5.1 → thinkphp:6.0', $steps[1]->label());
        $this->assertSame('thinkphp:6.0 → thinkphp:8.0', $steps[2]->label());
    }

    public function testFullMigrateAllControllers(): void
    {
        $steps = MigrationPlan::compute('3.2', '8.0');
        $allRules = [];
        foreach ($steps as $step) {
            $allRules = array_merge($allRules, $step->rules);
        }

        $transformer = new CodeTransformer();
        $finder = new Finder();
        $finder->files()->in($this->fixturePath)->name('*.php')
            ->notPath(['ThinkPHP', 'vendor']);

        $results = [];
        $syntaxErrors = [];

        foreach ($finder as $file) {
            $result = $transformer->transformFile($file->getRealPath(), $allRules, dryRun: true);
            if ($result->changed) {
                $results[] = $result;

                // Verify syntax
                $tmp = tempnam(sys_get_temp_dir(), 'tpup_int_');
                file_put_contents($tmp, $result->newCode);
                exec("php -l {$tmp} 2>&1", $output, $exitCode);
                unlink($tmp);

                if ($exitCode !== 0) {
                    $syntaxErrors[] = basename($result->filePath) . ': ' . implode(' ', $output);
                }
            }
        }

        // At least 3 files should change (GoodsController, OrderController, UserModel)
        $this->assertGreaterThanOrEqual(3, count($results));

        // All output must be syntactically valid
        $this->assertSame([], $syntaxErrors, "Syntax errors:\n" . implode("\n", $syntaxErrors));
    }

    public function testMigratedCodeHasNamespaces(): void
    {
        $steps = MigrationPlan::compute('3.2', '8.0');
        $allRules = [];
        foreach ($steps as $step) {
            $allRules = array_merge($allRules, $step->rules);
        }

        $transformer = new CodeTransformer();
        $file = $this->fixturePath . '/Application/Home/Controller/GoodsController.class.php';
        $result = $transformer->transformFile($file, $allRules, dryRun: true);

        $this->assertTrue($result->changed);

        // Positive assertions: correct replacements present
        $this->assertStringContainsString('namespace ', $result->newCode);
        $this->assertStringContainsString('\\think\\BaseController', $result->newCode);
        $this->assertStringContainsString('\\think\\facade\\View::fetch()', $result->newCode);
        $this->assertStringContainsString('\\think\\facade\\View::assign(', $result->newCode);
        $this->assertStringContainsString('\\app\\model\\Goods', $result->newCode);
        $this->assertStringContainsString('$request->get(', $result->newCode);

        // Negative assertions: old patterns removed
        $this->assertStringNotContainsString("M('Goods')", $result->newCode);
        $this->assertStringNotContainsString("I('get.", $result->newCode);
        $this->assertStringNotContainsString("IS_POST", $result->newCode);
        $this->assertStringNotContainsString("C('PAGE_SIZE')", $result->newCode);
        $this->assertStringNotContainsString('$this->display()', $result->newCode);
        $this->assertStringNotContainsString('$this->assign(', $result->newCode);
    }

    public function testTemplateMigratorOnFixture(): void
    {
        // Create a temp template fixture
        $tplContent = '<volist name="list" id="vo"><li>{$vo.name}</li></volist><script src="__PUBLIC__/js/app.js"></script>';
        $tmp = tempnam(sys_get_temp_dir(), 'tpup_tpl_');
        file_put_contents($tmp, $tplContent);

        $migrator = new TemplateMigrator();
        $result = $migrator->migrateFile($tmp, dryRun: true);
        unlink($tmp);

        $this->assertTrue($result->changed);
        $this->assertStringNotContainsString('<volist', $result->transformed);
        $this->assertStringNotContainsString('__PUBLIC__', $result->transformed);
        $this->assertStringContainsString('{volist', $result->transformed);
        $this->assertStringContainsString('/static', $result->transformed);
    }
}
