<?php

declare(strict_types=1);

namespace ThinkUpgrade\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use ThinkUpgrade\Template\TemplateMigrator;

final class TemplateMigratorTest extends TestCase
{
    private TemplateMigrator $migrator;

    protected function setUp(): void
    {
        $this->migrator = new TemplateMigrator();
    }

    public function testVolistConversion(): void
    {
        $input = '<volist name="list" id="vo"><li>{$vo.name}</li></volist>';
        $result = $this->migrator->migrate($input);

        $this->assertTrue($result->changed);
        $this->assertStringContainsString('{volist name="list" id="vo"}', $result->transformed);
        $this->assertStringContainsString('{/volist}', $result->transformed);
        $this->assertStringContainsString('{$vo.name}', $result->transformed);
    }

    public function testIfCondition(): void
    {
        $input = '<if condition="$user.status eq 1">Active<else/>Inactive</if>';
        $result = $this->migrator->migrate($input);

        $this->assertTrue($result->changed);
        $this->assertStringContainsString('{if condition="$user.status eq 1"}', $result->transformed);
        $this->assertStringContainsString('{else /}', $result->transformed);
        $this->assertStringContainsString('{/if}', $result->transformed);
    }

    public function testEqTag(): void
    {
        $input = '<eq name="type" value="admin">Admin Panel</eq>';
        $result = $this->migrator->migrate($input);

        $this->assertTrue($result->changed);
        $this->assertSame('{eq name="type" value="admin"}Admin Panel{/eq}', $result->transformed);
    }

    public function testInclude(): void
    {
        $input = '<include file="Public/header" />';
        $result = $this->migrator->migrate($input);

        $this->assertTrue($result->changed);
        $this->assertSame('{include file="Public/header" /}', $result->transformed);
    }

    public function testMagicConstants(): void
    {
        $input = '<link href="__PUBLIC__/css/style.css"><a href="__URL__/edit">Edit</a>';
        $result = $this->migrator->migrate($input);

        $this->assertTrue($result->changed);
        $this->assertStringContainsString('/static/css/style.css', $result->transformed);
        $this->assertStringContainsString("{:url('/')}/edit", $result->transformed);
    }

    public function testNoChangeOnAlreadyMigrated(): void
    {
        $input = '{volist name="list" id="vo"}{$vo.name}{/volist}';
        $result = $this->migrator->migrate($input);

        $this->assertFalse($result->changed);
        $this->assertSame([], $result->appliedRules);
    }

    public function testForeachConversion(): void
    {
        $input = '<foreach name="items" item="item">{$item}</foreach>';
        $result = $this->migrator->migrate($input);

        $this->assertTrue($result->changed);
        $this->assertStringContainsString('{foreach name="items" item="item"}', $result->transformed);
        $this->assertStringContainsString('{/foreach}', $result->transformed);
    }

    public function testComplexTemplate(): void
    {
        $input = '<include file="Public/header" />' . "\n"
            . '<volist name="list" id="vo">' . "\n"
            . '  <if condition="$vo.status eq 1">' . "\n"
            . '    <span class="active">{$vo.name}</span>' . "\n"
            . '  <else/>' . "\n"
            . '    <span class="inactive">{$vo.name}</span>' . "\n"
            . '  </if>' . "\n"
            . '</volist>' . "\n"
            . '<script src="__PUBLIC__/js/app.js"></script>';

        $result = $this->migrator->migrate($input);
        $this->assertTrue($result->changed);
        $this->assertStringNotContainsString('<volist', $result->transformed);
        $this->assertStringNotContainsString('<if', $result->transformed);
        $this->assertStringNotContainsString('__PUBLIC__', $result->transformed);
    }
}
