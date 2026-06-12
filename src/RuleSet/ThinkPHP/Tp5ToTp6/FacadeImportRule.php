<?php

declare(strict_types=1);

namespace PHPLift\RuleSet\ThinkPHP\Tp5ToTp6;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;
use PHPLift\RuleSet\RuleInterface;

/**
 * Convert known TP5 helper functions to Facade static calls:
 *   cache('key')   → \think\facade\Cache::get('key')
 *   session('key') → \think\facade\Session::get('key')
 *   cookie('key')  → \think\facade\Cookie::get('key')
 *
 * Only transforms the three known helpers. Arguments are preserved as-is.
 */
final class FacadeImportRule implements RuleInterface
{
    private const FUNC_MAP = [
        'cache' => ['think\\facade\\Cache', 'get'],
        'session' => ['think\\facade\\Session', 'get'],
        'cookie' => ['think\\facade\\Cookie', 'get'],
    ];

    public function name(): string { return 'tp5-facade-import'; }
    public function description(): string { return 'Convert helper functions to Facade calls'; }
    public function sourceVersion(): string { return 'thinkphp:5.1'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 35; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class(self::FUNC_MAP) extends NodeVisitorAbstract {
            public function __construct(private readonly array $funcMap) {}

            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof FuncCall || !$node->name instanceof Name) {
                    return null;
                }

                $funcName = $node->name->toString();
                $mapping = $this->funcMap[$funcName] ?? null;
                if ($mapping === null) {
                    return null;
                }

                [$class, $method] = $mapping;
                return new StaticCall(
                    new Name\FullyQualified($class),
                    $method,
                    $node->args,
                );
            }
        };
    }
}
