<?php

declare(strict_types=1);

namespace ThinkUpgrade\Tests;

use PHPUnit\Framework\TestCase;
use ThinkUpgrade\Analyzer\CodeAnalyzer;
use ThinkUpgrade\RuleSet\ThinkPHP\ConfigCallRule;
use ThinkUpgrade\RuleSet\ThinkPHP\ModelCallRule;

final class AnalyzerTest extends TestCase
{
    private CodeAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new CodeAnalyzer();
        $this->analyzer->setRules([new ModelCallRule(), new ConfigCallRule()]);
    }

    public function testDetectsModelCalls(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $analysis = $this->analyzer->analyzeFile($file);

        $this->assertTrue($analysis->hasIssues());

        $modelIssues = array_filter($analysis->issues, fn($i) => $i->ruleName === 'tp3-model-call');
        $this->assertGreaterThanOrEqual(3, count($modelIssues)); // M('User') x2, D('User') x1
    }

    public function testDetectsConfigCalls(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $analysis = $this->analyzer->analyzeFile($file);

        $configIssues = array_filter($analysis->issues, fn($i) => $i->ruleName === 'tp3-config-call');
        $this->assertGreaterThanOrEqual(2, count($configIssues)); // C('DB_HOST'), C('DB_NAME')
    }

    public function testAutoFixableCount(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $analysis = $this->analyzer->analyzeFile($file);

        $this->assertSame(count($analysis->issues), $analysis->autoFixableCount());
    }
}
