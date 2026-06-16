<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet\ThinkPHP\Tp6ToTp8;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;
use ThinkUpgrade\RuleSet\RuleInterface;

/**
 * Converts property declarations + constructor assignments to PHP 8 constructor promotion.
 *
 * Only promotes properties that are BOTH:
 * 1. Declared as class properties
 * 2. Assigned in constructor via exactly: $this->prop = $param (same name)
 *
 * Non-assignment statements in the constructor are preserved.
 * Classes without constructors are skipped.
 */
final class ConstructorPromotionRule implements RuleInterface
{
    public function name(): string { return 'tp6-constructor-promotion'; }
    public function description(): string { return 'Convert to PHP 8 constructor promotion'; }
    public function sourceVersion(): string { return 'thinkphp:6.0'; }
    public function targetVersion(): string { return 'thinkphp:8.0'; }
    public function priority(): int { return 70; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof Class_) {
                    return null;
                }

                $constructor = $this->findConstructor($node);
                if ($constructor === null || $constructor->params === []) {
                    return null;
                }

                // Collect declared properties: name → flags
                $declaredProps = [];
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Property) {
                        foreach ($stmt->props as $prop) {
                            $declaredProps[$prop->name->name] = $stmt->flags;
                        }
                    }
                }

                if ($declaredProps === []) {
                    return null;
                }

                // Find which params have matching $this->param = $param in constructor body
                $assignedInConstructor = $this->findSimpleAssignments($constructor);

                // Determine promotable: must be declared AND assigned via $this->x = $x
                $promotable = [];
                foreach ($constructor->params as $param) {
                    $paramName = $param->var->name;
                    if (isset($declaredProps[$paramName]) && isset($assignedInConstructor[$paramName])) {
                        $promotable[$paramName] = $declaredProps[$paramName];
                    }
                }

                if ($promotable === []) {
                    return null;
                }

                // Promote parameters by setting visibility flags
                foreach ($constructor->params as $param) {
                    $name = $param->var->name;
                    if (isset($promotable[$name])) {
                        $param->flags = $promotable[$name];
                    }
                }

                // Remove promoted property declarations from class body
                $node->stmts = array_values(array_filter($node->stmts, static function ($stmt) use ($promotable) {
                    if (!$stmt instanceof Property) {
                        return true;
                    }
                    foreach ($stmt->props as $prop) {
                        if (isset($promotable[$prop->name->name])) {
                            return false;
                        }
                    }
                    return true;
                }));

                // Remove $this->x = $x assignments from constructor body
                if ($constructor->stmts !== null) {
                    $constructor->stmts = array_values(array_filter($constructor->stmts, static function ($stmt) use ($promotable) {
                        if (!$stmt instanceof Expression || !$stmt->expr instanceof Assign) {
                            return true;
                        }
                        $assign = $stmt->expr;
                        if (!$assign->var instanceof PropertyFetch
                            || !$assign->var->var instanceof Variable
                            || $assign->var->var->name !== 'this'
                            || !$assign->var->name instanceof Identifier) {
                            return true;
                        }
                        return !isset($promotable[$assign->var->name->name]);
                    }));
                }

                return $node;
            }

            private function findConstructor(Class_ $class): ?ClassMethod
            {
                foreach ($class->stmts as $stmt) {
                    if ($stmt instanceof ClassMethod && $stmt->name->name === '__construct') {
                        return $stmt;
                    }
                }
                return null;
            }

            /**
             * Find simple assignments of form: $this->propName = $paramName
             * where propName === paramName. Returns [name => true].
             */
            private function findSimpleAssignments(ClassMethod $constructor): array
            {
                $result = [];
                foreach ($constructor->stmts ?? [] as $stmt) {
                    if (!$stmt instanceof Expression || !$stmt->expr instanceof Assign) {
                        continue;
                    }
                    $assign = $stmt->expr;
                    if (!$assign->var instanceof PropertyFetch
                        || !$assign->var->var instanceof Variable
                        || $assign->var->var->name !== 'this'
                        || !$assign->var->name instanceof Identifier) {
                        continue;
                    }
                    // RHS must be a simple variable with same name
                    if (!$assign->expr instanceof Variable || !is_string($assign->expr->name)) {
                        continue;
                    }
                    $propName = $assign->var->name->name;
                    $varName = $assign->expr->name;
                    if ($propName === $varName) {
                        $result[$propName] = true;
                    }
                }
                return $result;
            }
        };
    }
}
