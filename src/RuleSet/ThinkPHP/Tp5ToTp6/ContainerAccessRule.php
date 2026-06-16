<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet\ThinkPHP\Tp5ToTp6;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use ThinkUpgrade\RuleSet\RuleInterface;

/**
 * model('User') → \app\model\User::query()
 *
 * Only transforms when the first argument is a non-empty String_ literal
 * that is a valid PHP class name. Variables/expressions are skipped.
 */
final class ContainerAccessRule implements RuleInterface
{
    public function name(): string { return 'tp5-container-access'; }
    public function description(): string { return 'Update container access patterns for TP6'; }
    public function sourceVersion(): string { return 'thinkphp:5.1'; }
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

                if ($node->name->toString() !== 'model') {
                    return null;
                }

                $arg = $node->args[0] ?? null;
                if ($arg === null || !$arg->value instanceof String_) {
                    return null;
                }

                $modelName = trim($arg->value->value);
                if ($modelName === '' || !preg_match('/^[a-zA-Z_]\w*$/', $modelName)) {
                    return null;
                }

                return new StaticCall(
                    new Name\FullyQualified("app\\model\\{$modelName}"),
                    'query',
                );
            }
        };
    }
}
