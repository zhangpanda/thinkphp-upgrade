<?php

declare(strict_types=1);

namespace PHPLift\RuleSet\ThinkPHP;

use PhpParser\Node;
use PhpParser\Node\Name as NodeName;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use PHPLift\RuleSet\RuleInterface;

/**
 * Adds a namespace to TP3 files that lack one.
 *
 * Guarantees:
 * - declare(strict_types=1) stays BEFORE the namespace statement, not inside it
 * - InlineHTML (opening <?php tag) stays before namespace
 * - Skips files that already have a namespace
 * - Validates the target namespace is non-empty
 */
final class AddNamespaceRule implements RuleInterface
{
    public function __construct(private readonly string $namespace = 'app\\controller') {}

    public function name(): string { return 'tp3-add-namespace'; }
    public function description(): string { return 'Add namespace to TP3 classes'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 0; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        $ns = $this->namespace;
        return new class($ns) extends NodeVisitorAbstract {
            private bool $hasNamespace = false;

            public function __construct(private readonly string $ns) {}

            public function beforeTraverse(array $nodes): ?array
            {
                $this->hasNamespace = false;
                foreach ($nodes as $node) {
                    if ($node instanceof Stmt\Namespace_) {
                        $this->hasNamespace = true;
                        break;
                    }
                }
                return null;
            }

            public function afterTraverse(array $nodes): ?array
            {
                if ($this->hasNamespace || $this->ns === '') {
                    return null;
                }

                // Separate: statements that must stay BEFORE namespace vs inside
                $before = [];
                $inside = [];
                foreach ($nodes as $node) {
                    if ($node instanceof Stmt\Declare_
                        || $node instanceof Stmt\InlineHTML) {
                        $before[] = $node;
                    } else {
                        $inside[] = $node;
                    }
                }

                if ($inside === []) {
                    return null;
                }

                $nsNode = new Stmt\Namespace_(
                    new NodeName($this->ns),
                    $inside,
                );

                return [...$before, $nsNode];
            }
        };
    }
}
