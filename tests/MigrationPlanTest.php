<?php

declare(strict_types=1);

namespace ThinkUpgrade\Tests;

use PHPUnit\Framework\TestCase;
use ThinkUpgrade\Engine\MigrationPlan;
use ThinkUpgrade\RuleSet\ThinkPHP\Tp5ToTp6\ContainerAccessRule;
use ThinkUpgrade\RuleSet\ThinkPHP\Tp5ToTp6\FacadeImportRule;
use ThinkUpgrade\RuleSet\ThinkPHP\Tp5ToTp6\ModuleRemovalRule;
use ThinkUpgrade\RuleSet\ThinkPHP\Tp6ToTp8\ConstructorPromotionRule;
use ThinkUpgrade\RuleSet\ThinkPHP\Tp6ToTp8\MatchExpressionRule;
use ThinkUpgrade\RuleSet\ThinkPHP\Tp6ToTp8\TypedPropertyRule;
use ThinkUpgrade\Transformer\CodeTransformer;

final class MigrationPlanTest extends TestCase
{
    public function testComputesTp3ToTp8Path(): void
    {
        $steps = MigrationPlan::compute('3.2', '8.0');

        $this->assertCount(3, $steps);
        $this->assertSame('thinkphp:3.2', $steps[0]->from);
        $this->assertSame('thinkphp:5.1', $steps[0]->to);
        $this->assertSame('thinkphp:5.1', $steps[1]->from);
        $this->assertSame('thinkphp:6.0', $steps[1]->to);
        $this->assertSame('thinkphp:6.0', $steps[2]->from);
        $this->assertSame('thinkphp:8.0', $steps[2]->to);
    }

    public function testComputesTp5ToTp6Path(): void
    {
        $steps = MigrationPlan::compute('5.1', '6.0');

        $this->assertCount(1, $steps);
        $this->assertSame('thinkphp:5.1', $steps[0]->from);
        $this->assertNotEmpty($steps[0]->rules);
    }

    public function testComputesTp6ToTp8Path(): void
    {
        $steps = MigrationPlan::compute('6.0', '8.0');

        $this->assertCount(1, $steps);
        $this->assertSame('thinkphp:8.0', $steps[0]->to);
    }

    public function testReturnsEmptyForSameVersion(): void
    {
        $this->assertEmpty(MigrationPlan::compute('6.0', '6.0'));
    }

    public function testReturnsEmptyForDowngrade(): void
    {
        $this->assertEmpty(MigrationPlan::compute('8.0', '3.2'));
    }

    public function testHandlesVersionPrefix(): void
    {
        $steps = MigrationPlan::compute('thinkphp:3.2', 'thinkphp:6.0');
        $this->assertCount(2, $steps);
    }

    public function testModuleRemovalRule(): void
    {
        $code = "<?php\nnamespace app\\admin\\controller;\nclass User {}\n";
        $tmp = $this->writeTmp($code);

        $transformer = new CodeTransformer();
        $result = $transformer->transformFile($tmp, [new ModuleRemovalRule()], true);
        unlink($tmp);

        $this->assertStringContainsString('namespace app\\controller', $result->newCode);
        $this->assertStringNotContainsString('admin', $result->newCode);
    }

    public function testFacadeImportRule(): void
    {
        $code = "<?php\n\$v = cache('key');\nsession('user');\n";
        $tmp = $this->writeTmp($code);

        $transformer = new CodeTransformer();
        $result = $transformer->transformFile($tmp, [new FacadeImportRule()], true);
        unlink($tmp);

        $this->assertStringContainsString('\\think\\facade\\Cache::get', $result->newCode);
        $this->assertStringContainsString('\\think\\facade\\Session::get', $result->newCode);
    }

    public function testContainerAccessRule(): void
    {
        $code = "<?php\n\$user = model('User');\n";
        $tmp = $this->writeTmp($code);

        $transformer = new CodeTransformer();
        $result = $transformer->transformFile($tmp, [new ContainerAccessRule()], true);
        unlink($tmp);

        $this->assertStringContainsString('\\app\\model\\User::query()', $result->newCode);
    }

    public function testTypedPropertyRule(): void
    {
        $code = "<?php\nclass A {\n    protected \$name = '';\n    protected \$items = [];\n    protected \$count = 0;\n}\n";
        $tmp = $this->writeTmp($code);

        $transformer = new CodeTransformer();
        $result = $transformer->transformFile($tmp, [new TypedPropertyRule()], true);
        unlink($tmp);

        $this->assertStringContainsString('protected string $name', $result->newCode);
        $this->assertStringContainsString('protected array $items', $result->newCode);
        $this->assertStringContainsString('protected int $count', $result->newCode);
    }

    public function testConstructorPromotionRule(): void
    {
        $code = <<<'PHP'
<?php
class UserService {
    protected $repo;
    public function __construct(UserRepo $repo) {
        $this->repo = $repo;
    }
}
PHP;
        $tmp = $this->writeTmp($code);

        $transformer = new CodeTransformer();
        $result = $transformer->transformFile($tmp, [new ConstructorPromotionRule()], true);
        unlink($tmp);

        $this->assertStringContainsString('protected UserRepo $repo', $result->newCode);
        $this->assertStringNotContainsString('$this->repo = $repo', $result->newCode);
    }

    public function testMatchExpressionRule(): void
    {
        $code = <<<'PHP'
<?php
function getLabel($status) {
    switch($status) {
        case 1: return 'pending';
        case 2: return 'paid';
        default: return 'unknown';
    }
}
PHP;
        $tmp = $this->writeTmp($code);

        $transformer = new CodeTransformer();
        $result = $transformer->transformFile($tmp, [new MatchExpressionRule()], true);
        unlink($tmp);

        $this->assertStringContainsString('match', $result->newCode);
        $this->assertStringNotContainsString('switch', $result->newCode);
    }

    private function writeTmp(string $code): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phplift_');
        file_put_contents($tmp, $code);
        return $tmp;
    }
}
