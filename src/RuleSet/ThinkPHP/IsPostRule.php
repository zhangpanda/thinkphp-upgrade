<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet\ThinkPHP;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\NodeVisitorAbstract;
use ThinkUpgrade\RuleSet\RuleInterface;

/**
 * IS_POST → $request->isPost()
 * IS_GET  → $request->isGet()
 * IS_AJAX → $request->isAjax()
 */
final class IsPostRule implements RuleInterface
{
    private const MAP = [
        'IS_POST' => 'isPost',
        'IS_GET' => 'isGet',
        'IS_AJAX' => 'isAjax',
    ];

    public function name(): string { return 'tp3-is-post'; }
    public function description(): string { return 'Convert IS_POST/IS_GET/IS_AJAX to Request methods'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 42; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class(self::MAP) extends NodeVisitorAbstract {
            public function __construct(private readonly array $map) {}

            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof ConstFetch) {
                    return null;
                }

                $name = $node->name->toString();
                $method = $this->map[$name] ?? null;
                if ($method === null) {
                    return null;
                }

                return new MethodCall(
                    new Variable('request'),
                    new Identifier($method),
                );
            }
        };
    }
}
