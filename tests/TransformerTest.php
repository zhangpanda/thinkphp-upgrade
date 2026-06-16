<?php

declare(strict_types=1);

namespace ThinkUpgrade\Tests;

use PHPUnit\Framework\TestCase;
use ThinkUpgrade\RuleSet\ThinkPHP\ConfigCallRule;
use ThinkUpgrade\RuleSet\ThinkPHP\ModelCallRule;
use ThinkUpgrade\Transformer\CodeTransformer;

final class TransformerTest extends TestCase
{
    private CodeTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new CodeTransformer();
    }

    public function testTransformsModelCalls(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $result = $this->transformer->transformFile($file, [new ModelCallRule()], dryRun: true);

        $this->assertTrue($result->changed);
        $this->assertStringContainsString('\\app\\model\\User', $result->newCode);
        $this->assertStringNotContainsString("M('User')", $result->newCode);
        $this->assertStringNotContainsString("D('User')", $result->newCode);
    }

    public function testTransformsConfigCalls(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $result = $this->transformer->transformFile($file, [new ConfigCallRule()], dryRun: true);

        $this->assertTrue($result->changed);
        $this->assertStringContainsString("config('database.connections.mysql.hostname')", $result->newCode);
        $this->assertStringContainsString("config('database.connections.mysql.database')", $result->newCode);
        $this->assertStringNotContainsString("C('DB_HOST')", $result->newCode);
    }

    public function testDryRunDoesNotWriteFile(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $original = file_get_contents($file);

        $this->transformer->transformFile($file, [new ModelCallRule(), new ConfigCallRule()], dryRun: true);

        $this->assertSame($original, file_get_contents($file));
    }

    public function testCombinedRules(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $result = $this->transformer->transformFile($file, [new ModelCallRule(), new ConfigCallRule()], dryRun: true);

        $this->assertTrue($result->changed);
        $this->assertCount(2, $result->appliedRules);
        // Both M/D and C should be converted
        $this->assertStringContainsString('\\app\\model\\User', $result->newCode);
        $this->assertStringContainsString("config(", $result->newCode);
    }
}
