<?php

declare(strict_types=1);

namespace ThinkUpgrade\Tests;

use PHPUnit\Framework\TestCase;
use ThinkUpgrade\Scanner\FrameworkInfo;
use ThinkUpgrade\Scanner\ProjectProfile;
use ThinkUpgrade\Scanner\ProjectScanner;

final class ScannerTest extends TestCase
{
    public function testScansFixtureProject(): void
    {
        $scanner = new ProjectScanner();
        $profile = $scanner->scan(__DIR__ . '/Fixtures/tp32-sample');

        $this->assertInstanceOf(ProjectProfile::class, $profile);
        $this->assertGreaterThan(0, $profile->phpFiles);
        $this->assertGreaterThan(0, $profile->totalLines);
    }

    public function testDetectsUnknownFrameworkForFixture(): void
    {
        $scanner = new ProjectScanner();
        $profile = $scanner->scan(__DIR__ . '/Fixtures/tp32-sample');

        // No composer.json or ThinkPHP.php in fixture, so unknown
        $this->assertSame('unknown', $profile->framework->name);
    }
}
