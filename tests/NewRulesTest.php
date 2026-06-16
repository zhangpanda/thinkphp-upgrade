<?php

declare(strict_types=1);

namespace ThinkUpgrade\Tests;

use PHPUnit\Framework\TestCase;
use ThinkUpgrade\RuleSet\ThinkPHP\InputCallRule;
use ThinkUpgrade\RuleSet\ThinkPHP\IsPostRule;
use ThinkUpgrade\Transformer\CodeTransformer;

final class NewRulesTest extends TestCase
{
    private CodeTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new CodeTransformer();
    }

    public function testInputCallRuleConvertsIToRequest(): void
    {
        $file = __DIR__ . '/Fixtures/tp32-sample/UserController.class.php';
        $result = $this->transformer->transformFile($file, [new InputCallRule()], dryRun: true);

        $this->assertTrue($result->changed);
        $this->assertStringContainsString('$request->get(', $result->newCode);
        $this->assertStringNotContainsString("I('get.id'", $result->newCode);
    }

    public function testIsPostRuleConvertsConstants(): void
    {
        // Create a temp file with IS_POST
        $code = "<?php\nif (IS_POST) { echo 'yes'; }\n";
        $tmp = tempnam(sys_get_temp_dir(), 'tpup_');
        file_put_contents($tmp, $code);

        $result = $this->transformer->transformFile($tmp, [new IsPostRule()], dryRun: true);
        unlink($tmp);

        $this->assertTrue($result->changed);
        $this->assertStringContainsString('$request->isPost()', $result->newCode);
        $this->assertStringNotContainsString('IS_POST', $result->newCode);
    }

    public function testInputCallRuleMetadata(): void
    {
        $rule = new InputCallRule();
        $this->assertSame('tp3-input-call', $rule->name());
        $this->assertSame(38, $rule->priority());
    }

    public function testIsPostRuleMetadata(): void
    {
        $rule = new IsPostRule();
        $this->assertSame('tp3-is-post', $rule->name());
        $this->assertSame(42, $rule->priority());
    }
}
