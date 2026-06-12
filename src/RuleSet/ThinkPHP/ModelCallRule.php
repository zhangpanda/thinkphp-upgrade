<?php

declare(strict_types=1);

namespace PHPLift\RuleSet\ThinkPHP;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use PHPLift\RuleSet\RuleInterface;

/**
 * M('User') → \app\model\User::query()
 * D('Order') → \app\model\Order::query()
 *
 * Only transforms when the first argument is a non-empty String_ literal.
 * Variables and expressions are left untouched.
 */
final class ModelCallRule implements RuleInterface
{
    public function name(): string { return 'tp3-model-call'; }
    public function description(): string { return 'Convert M()/D() to model class static calls'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 30; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof FuncCall || !$node->name instanceof Name) {
                    return null;
                }

                $funcName = $node->name->toString();
                if ($funcName !== 'M' && $funcName !== 'D') {
                    return null;
                }

                $arg = $node->args[0] ?? null;
                if ($arg === null || !$arg->value instanceof String_) {
                    return null;
                }

                $modelName = trim($arg->value->value);
                if ($modelName === '') {
                    return null;
                }

                // Validate: model name must be a valid PHP identifier (PascalCase expected)
                if (!preg_match('/^[a-zA-Z_]\w*$/', $modelName)) {
                    return null;
                }

                $fqcn = "app\\model\\{$modelName}";

                // D() returns model instance → new \app\model\User()
                // M() returns query builder → \app\model\User::query()
                if ($funcName === 'D') {
                    return new Node\Expr\New_(
                        new Name\FullyQualified($fqcn),
                    );
                }

                return new StaticCall(
                    new Name\FullyQualified($fqcn),
                    'query',
                );
            }
        };
    }
}
