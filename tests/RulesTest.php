<?php

declare(strict_types=1);

namespace PHPLift\Tests;

use PHPUnit\Framework\TestCase;
use PHPLift\RuleSet\ThinkPHP\ConfigCallRule;
use PHPLift\RuleSet\ThinkPHP\ModelCallRule;

final class RulesTest extends TestCase
{
    public function testModelCallRuleMetadata(): void
    {
        $rule = new ModelCallRule();

        $this->assertSame('tp3-model-call', $rule->name());
        $this->assertSame('thinkphp:3.2', $rule->sourceVersion());
        $this->assertSame('thinkphp:6.0', $rule->targetVersion());
        $this->assertSame(30, $rule->priority());
        $this->assertTrue($rule->isAutoFixable());
    }

    public function testConfigCallRuleMetadata(): void
    {
        $rule = new ConfigCallRule();

        $this->assertSame('tp3-config-call', $rule->name());
        $this->assertSame(35, $rule->priority());
        $this->assertTrue($rule->isAutoFixable());
    }

    public function testModelCallRuleReturnsVisitor(): void
    {
        $rule = new ModelCallRule();
        $visitor = $rule->getTransformVisitor();

        $this->assertInstanceOf(\PhpParser\NodeVisitorAbstract::class, $visitor);
    }

    public function testConfigCallRuleReturnsVisitor(): void
    {
        $rule = new ConfigCallRule();
        $visitor = $rule->getTransformVisitor();

        $this->assertInstanceOf(\PhpParser\NodeVisitorAbstract::class, $visitor);
    }
}
