<?php

declare(strict_types=1);

namespace PHPLift\Tests;

use PHPUnit\Framework\TestCase;
use PHPLift\AI\AIAssistManager;
use PHPLift\AI\AISuggestion;
use PHPLift\AI\MigrationContext;
use PHPLift\AI\OpenAIProvider;
use PHPLift\Analyzer\FileAnalysis;
use PHPLift\Analyzer\Issue;
use PHPLift\Reporter\ConsoleReporter;
use PHPLift\Reporter\JsonReporter;

final class AIAndReporterTest extends TestCase
{
    public function testAIAssistManagerFallsBackWhenNoProvider(): void
    {
        $manager = new AIAssistManager();
        $context = new MigrationContext(
            originalCode: '$x = M("User");',
            filePath: 'test.php',
            fromFramework: 'thinkphp:3.2',
            toFramework: 'thinkphp:6.0',
        );

        $suggestion = $manager->suggest($context);

        $this->assertSame('$x = M("User");', $suggestion->suggestedCode);
        $this->assertSame(0.0, $suggestion->confidence);
        $this->assertTrue($suggestion->needsReview);
    }

    public function testAIAssistManagerDetectsNoAvailableProvider(): void
    {
        $manager = new AIAssistManager();
        $this->assertFalse($manager->hasAvailableProvider());
    }

    public function testOpenAIProviderIsUnavailableWithoutKey(): void
    {
        $provider = new OpenAIProvider(apiKey: '');
        $this->assertFalse($provider->isAvailable());
    }

    public function testOpenAIProviderIsAvailableWithKey(): void
    {
        $provider = new OpenAIProvider(apiKey: 'sk-test');
        $this->assertTrue($provider->isAvailable());
    }

    public function testJsonReporter(): void
    {
        $analyses = [
            new FileAnalysis('a.php', [
                new Issue('tp3-model-call', 'M() call', 5, "M('User')"),
                new Issue('tp3-config-call', 'C() call', 10, "C('DB_HOST')"),
            ]),
            new FileAnalysis('b.php', []),
        ];

        $reporter = new JsonReporter();
        $output = $reporter->generate($analyses);
        $data = json_decode($output, true);

        $this->assertSame(2, $data['summary']['files']);
        $this->assertSame(2, $data['summary']['total_issues']);
        $this->assertSame(2, $data['summary']['auto_fixable']);
        $this->assertCount(1, $data['files']); // b.php has no issues
    }

    public function testConsoleReporter(): void
    {
        $analyses = [
            new FileAnalysis('a.php', [
                new Issue('tp3-model-call', 'M() call', 5, "M('User')"),
            ]),
        ];

        $reporter = new ConsoleReporter();
        $output = $reporter->generate($analyses);

        $this->assertStringContainsString('PHPLift Migration Report', $output);
        $this->assertStringContainsString('Auto-fixable:  1', $output);
        $this->assertStringContainsString('tp3-model-call: 1', $output);
    }
}
