<?php

declare(strict_types=1);

namespace ThinkUpgrade\Engine;

use ThinkUpgrade\RuleSet\RuleInterface;
use ThinkUpgrade\RuleSet\ThinkPHP;

/**
 * Computes the migration path between any two ThinkPHP versions
 * and returns the ordered rule sets for each step.
 *
 * Supported paths:
 *   TP3.2 → TP5.1 → TP6.0 → TP8.0
 */
final class MigrationPlan
{
    /** Version sequence — must be in order */
    private const PATH = ['3.2', '5.1', '6.0', '8.0'];

    /** @var array<string, string> step key → method name */
    private const RULE_MAP = [
        '3.2->5.1' => 'tp3ToTp5Rules',
        '5.1->6.0' => 'tp5ToTp6Rules',
        '6.0->8.0' => 'tp6ToTp8Rules',
    ];

    /**
     * @return list<MigrationStep>
     */
    public static function compute(string $fromVersion, string $toVersion): array
    {
        $from = self::normalize($fromVersion);
        $to = self::normalize($toVersion);

        if ($from === '' || $to === '') {
            return [];
        }

        $fromIdx = array_search($from, self::PATH, true);
        $toIdx = array_search($to, self::PATH, true);

        if ($fromIdx === false || $toIdx === false || $fromIdx >= $toIdx) {
            return [];
        }

        $steps = [];
        for ($i = $fromIdx; $i < $toIdx; $i++) {
            $stepFrom = self::PATH[$i];
            $stepTo = self::PATH[$i + 1];
            $key = "{$stepFrom}->{$stepTo}";
            $method = self::RULE_MAP[$key] ?? null;

            if ($method !== null) {
                $rules = self::$method();
                usort($rules, fn(RuleInterface $a, RuleInterface $b) => $a->priority() <=> $b->priority());
                $steps[] = new MigrationStep(
                    from: "thinkphp:{$stepFrom}",
                    to: "thinkphp:{$stepTo}",
                    rules: $rules,
                );
            }
        }

        return $steps;
    }

    /** @return list<string> */
    public static function getSupportedVersions(): array
    {
        return self::PATH;
    }

    /**
     * Normalize version strings:
     *   "thinkphp:3.2.3" → "3.2"
     *   "3.2.3" → "3.2"
     *   "3.2" → "3.2"
     */
    public static function normalize(string $version): string
    {
        $v = str_replace('thinkphp:', '', trim($version));

        if ($v === '' || !preg_match('/^\d+(\.\d+){0,2}$/', $v)) {
            return '';
        }

        $parts = explode('.', $v);
        $result = $parts[0] . '.' . ($parts[1] ?? '0');

        // Canonical version mapping
        $canonicalMap = [
            '3.0' => '3.2', '3.1' => '3.2',
            '5.0' => '5.1',
            '6.1' => '6.0', '6.2' => '6.0',
            '8.1' => '8.0',
        ];
        $result = $canonicalMap[$result] ?? $result;

        return $result;
    }

    /** @return list<RuleInterface> */
    private static function tp3ToTp5Rules(): array
    {
        return [
            new ThinkPHP\AddNamespaceRule('app\\controller'),
            new ThinkPHP\ControllerMigrationRule(),
            new ThinkPHP\ModelCallRule(),
            new ThinkPHP\ConfigCallRule(),
            new ThinkPHP\InputCallRule(),
            new ThinkPHP\UrlGenerateRule(),
            new ThinkPHP\IsPostRule(),
        ];
    }

    /** @return list<RuleInterface> */
    private static function tp5ToTp6Rules(): array
    {
        return [
            new ThinkPHP\Tp5ToTp6\ModuleRemovalRule(),
            new ThinkPHP\Tp5ToTp6\ContainerAccessRule(),
            new ThinkPHP\Tp5ToTp6\FacadeImportRule(),
        ];
    }

    /** @return list<RuleInterface> */
    private static function tp6ToTp8Rules(): array
    {
        return [
            new ThinkPHP\Tp6ToTp8\ConstructorPromotionRule(),
            new ThinkPHP\Tp6ToTp8\TypedPropertyRule(),
            new ThinkPHP\Tp6ToTp8\MatchExpressionRule(),
        ];
    }
}
