<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet\ThinkPHP\Tp6ToTp8;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;
use ThinkUpgrade\RuleSet\RuleInterface;

/**
 * Adds type declarations to untyped properties based on default values:
 *
 *   protected $name = '';    → protected string $name = '';
 *   protected $count = 0;   → protected int $count = 0;
 *   protected $items = [];  → protected array $items = [];
 *   protected $rate = 0.0;  → protected float $rate = 0.0;
 *   protected $flag = true; → protected bool $flag = true;
 *
 * Skips:
 * - Properties that already have type declarations
 * - Properties with null default (ambiguous type)
 * - Properties with non-inferrable defaults (variables, function calls, etc.)
 */
final class TypedPropertyRule implements RuleInterface
{
    public function name(): string { return 'tp6-typed-property'; }
    public function description(): string { return 'Add type declarations to properties from defaults'; }
    public function sourceVersion(): string { return 'thinkphp:6.0'; }
    public function targetVersion(): string { return 'thinkphp:8.0'; }
    public function priority(): int { return 75; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof Property) {
                    return null;
                }

                // Skip if already typed
                if ($node->type !== null) {
                    return null;
                }

                $prop = $node->props[0] ?? null;
                if ($prop === null || $prop->default === null) {
                    return null;
                }

                $default = $prop->default;

                // Skip null defaults (ConstFetch 'null') — ambiguous type
                if ($default instanceof ConstFetch
                    && $default->name->toLowerString() === 'null') {
                    return null;
                }

                $type = match (true) {
                    $default instanceof Scalar\String_ => 'string',
                    $default instanceof Scalar\Int_ => 'int',
                    $default instanceof Scalar\Float_ => 'float',
                    $default instanceof Array_ => 'array',
                    $default instanceof ConstFetch
                        && in_array($default->name->toLowerString(), ['true', 'false'], true) => 'bool',
                    default => null,
                };

                if ($type === null) {
                    return null;
                }

                $node->type = new Identifier($type);
                return $node;
            }
        };
    }
}
