<?php

declare(strict_types=1);

namespace PHPLift\Tests;

use PHPUnit\Framework\TestCase;
use PHPLift\RuleSet\ThinkPHP\AddNamespaceRule;
use PHPLift\RuleSet\ThinkPHP\ConfigCallRule;
use PHPLift\RuleSet\ThinkPHP\ControllerMigrationRule;
use PHPLift\RuleSet\ThinkPHP\ModelCallRule;
use PHPLift\RuleSet\ThinkPHP\UrlGenerateRule;
use PHPLift\Transformer\CodeTransformer;

final class EndToEndTest extends TestCase
{
    private CodeTransformer $transformer;
    private array $allRules;

    protected function setUp(): void
    {
        $this->transformer = new CodeTransformer();
        $this->allRules = [
            new AddNamespaceRule(),
            new ControllerMigrationRule(),
            new ModelCallRule(),
            new ConfigCallRule(),
            new UrlGenerateRule(),
        ];
    }

    public function testFullTp3ToTp6Migration(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $result = $this->transformer->transformFile($file, $this->allRules, dryRun: true);

        $this->assertTrue($result->changed);

        // Namespace added
        $this->assertStringContainsString('namespace app\\controller;', $result->newCode);

        // Controller extends changed
        $this->assertStringContainsString('\\think\\BaseController', $result->newCode);
        $this->assertStringNotContainsString('extends Controller', $result->newCode);

        // M()/D() converted to model static calls
        $this->assertStringNotContainsString("M('User')", $result->newCode);
        $this->assertStringNotContainsString("D('User')", $result->newCode);
        $this->assertStringContainsString('\\app\\model\\User', $result->newCode);

        // C() converted to config()
        $this->assertStringNotContainsString("C('DB_HOST')", $result->newCode);
        $this->assertStringContainsString("config('database.connections.mysql.hostname')", $result->newCode);

        // U() converted to url()
        $this->assertStringNotContainsString("U('Admin/User/index')", $result->newCode);
        $this->assertStringContainsString("url('user/index')", $result->newCode);

        // $this->display() → View::fetch()
        $this->assertStringNotContainsString('$this->display()', $result->newCode);
        $this->assertStringContainsString('\\think\\facade\\View::fetch()', $result->newCode);

        // $this->assign() → View::assign()
        $this->assertStringNotContainsString('$this->assign(', $result->newCode);
        $this->assertStringContainsString('\\think\\facade\\View::assign(', $result->newCode);
    }

    public function testAllRulesApplied(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $result = $this->transformer->transformFile($file, $this->allRules, dryRun: true);

        $this->assertCount(5, $result->appliedRules);
        $this->assertContains('tp3-add-namespace', $result->appliedRules);
        $this->assertContains('tp3-controller-migration', $result->appliedRules);
        $this->assertContains('tp3-model-call', $result->appliedRules);
        $this->assertContains('tp3-config-call', $result->appliedRules);
        $this->assertContains('tp3-url-generate', $result->appliedRules);
    }

    public function testOutputIsSyntacticallyValid(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $result = $this->transformer->transformFile($file, $this->allRules, dryRun: true);

        // Write to temp and verify syntax
        $tmp = tempnam(sys_get_temp_dir(), 'phplift_test_');
        file_put_contents($tmp, $result->newCode);

        exec("php -l {$tmp} 2>&1", $output, $exitCode);
        unlink($tmp);

        $this->assertSame(0, $exitCode, "Syntax error in output:\n" . implode("\n", $output));
    }
}
