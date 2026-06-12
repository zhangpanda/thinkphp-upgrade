<?php

declare(strict_types=1);

namespace PHPLift\Tests;

use PHPUnit\Framework\TestCase;
use PHPLift\Engine\MigrationPlan;
use PHPLift\Transformer\CodeTransformer;

/**
 * Tests the full TP3→TP5→TP6→TP8 migration chain.
 */
final class FullChainTest extends TestCase
{
    public function testTp3ToTp8FullChain(): void
    {
        $code = <<<'PHP'
<?php
namespace app\admin\controller;

class OrderService
{
    protected $repo;
    protected $name = '';
    protected $items = [];

    public function __construct(OrderRepo $repo)
    {
        $this->repo = $repo;
    }

    public function list()
    {
        $list = model('Order');
        $cached = cache('orders');
        return $list;
    }
}
PHP;
        $tmp = tempnam(sys_get_temp_dir(), 'phplift_chain_');
        file_put_contents($tmp, $code);

        $steps = MigrationPlan::compute('3.2', '8.0');
        $this->assertCount(3, $steps);

        $allRules = [];
        foreach ($steps as $step) {
            $allRules = array_merge($allRules, $step->rules);
        }

        $transformer = new CodeTransformer();
        $result = $transformer->transformFile($tmp, $allRules, true);
        unlink($tmp);

        $this->assertTrue($result->changed);

        // TP5→TP6: module removal (app\admin\controller → app\controller)
        $this->assertStringContainsString('namespace app\\controller', $result->newCode);
        $this->assertStringNotContainsString('admin', $result->newCode);

        // TP5→TP6: model() → static call
        $this->assertStringContainsString('\\app\\model\\Order::query()', $result->newCode);
        $this->assertStringNotContainsString("model('Order')", $result->newCode);

        // TP5→TP6: cache() → Facade
        $this->assertStringContainsString('\\think\\facade\\Cache::get', $result->newCode);

        // TP6→TP8: typed properties
        $this->assertStringContainsString('protected string $name', $result->newCode);
        $this->assertStringContainsString('protected array $items', $result->newCode);

        // TP6→TP8: constructor promotion
        $this->assertStringContainsString('protected OrderRepo $repo', $result->newCode);
        $this->assertStringNotContainsString('$this->repo = $repo', $result->newCode);

        // Verify syntax is valid
        $tmp2 = tempnam(sys_get_temp_dir(), 'phplift_lint_');
        file_put_contents($tmp2, $result->newCode);
        exec("php -l {$tmp2} 2>&1", $out, $exitCode);
        unlink($tmp2);
        $this->assertSame(0, $exitCode, "Syntax error:\n" . implode("\n", $out));
    }

    public function testTp3ToTp6PartialChain(): void
    {
        $steps = MigrationPlan::compute('3.2', '6.0');
        $this->assertCount(2, $steps);
        $this->assertSame('thinkphp:3.2', $steps[0]->from);
        $this->assertSame('thinkphp:5.1', $steps[0]->to);
        $this->assertSame('thinkphp:5.1', $steps[1]->from);
        $this->assertSame('thinkphp:6.0', $steps[1]->to);

        // TP3→TP5 step should have 7 rules
        $this->assertCount(7, $steps[0]->rules);
        // TP5→TP6 step should have 3 rules
        $this->assertCount(3, $steps[1]->rules);
    }

    public function testNormalizesVersionStrings(): void
    {
        // "thinkphp:3.2.3" → computes same as "3.2"
        $a = MigrationPlan::compute('thinkphp:3.2.3', '8.0');
        $b = MigrationPlan::compute('3.2', '8.0');
        $this->assertCount(count($b), $a);

        // TP5.0 normalizes to 5.1
        $steps = MigrationPlan::compute('5.0', '6.0');
        $this->assertCount(1, $steps);
    }
}
