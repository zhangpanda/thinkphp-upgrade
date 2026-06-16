<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet\ThinkPHP;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitorAbstract;
use ThinkUpgrade\RuleSet\RuleInterface;

/**
 * - extends Controller → extends \think\BaseController (only "Controller", not Model/Service)
 * - $this->display(...) → View::fetch(...)
 * - $this->assign(...) → View::assign(...)
 *
 * Arguments on display()/assign() are preserved as-is.
 */
final class ControllerMigrationRule implements RuleInterface
{
    public function name(): string { return 'tp3-controller-migration'; }
    public function description(): string { return 'Migrate Controller: extends, display(), assign()'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 15; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                // extends Controller → extends \think\BaseController
                if ($node instanceof Class_ && $node->extends !== null) {
                    $parentName = $node->extends->toString();
                    // Skip already-migrated classes
                    if ($parentName === 'think\\BaseController' || $parentName === '\\think\\BaseController') {
                        return null;
                    }
                    // Only transform if extending exactly "Controller" (not Model, Service, etc.)
                    if ($parentName === 'Controller' || $parentName === 'Think\\Controller') {
                        $node->extends = new Name\FullyQualified('think\\BaseController');
                        return $node;
                    }
                    return null;
                }

                // $this->display(...) → View::fetch(...)
                if ($this->isThisCall($node, 'display')) {
                    /** @var MethodCall $node */
                    return new StaticCall(
                        new Name\FullyQualified('think\\facade\\View'),
                        'fetch',
                        $node->args,
                    );
                }

                // $this->assign(...) → View::assign(...)
                if ($this->isThisCall($node, 'assign')) {
                    /** @var MethodCall $node */
                    return new StaticCall(
                        new Name\FullyQualified('think\\facade\\View'),
                        'assign',
                        $node->args,
                    );
                }

                // Skip if already using View:: static calls (idempotency)
                if ($node instanceof StaticCall
                    && $node->class instanceof Name
                    && str_contains($node->class->toString(), 'View')) {
                    return null;
                }

                return null;
            }

            private function isThisCall(Node $node, string $method): bool
            {
                return $node instanceof MethodCall
                    && $node->var instanceof Variable
                    && $node->var->name === 'this'
                    && $node->name instanceof Identifier
                    && $node->name->name === $method;
            }
        };
    }
}
