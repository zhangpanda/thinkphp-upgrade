<?php

declare(strict_types=1);

namespace PHPLift\RuleSet\ThinkPHP\Tp6ToTp8;

use PhpParser\Node;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\NodeVisitorAbstract;
use PHPLift\RuleSet\RuleInterface;

/**
 * Converts simple switch/case to PHP 8 match expression.
 *
 * Only converts when EVERY case:
 * - Has exactly 1 statement (a Return_ with an expression)
 * - No break statements, no fall-through, no multiple statements
 *
 * Result: return match($cond) { ... };
 */
final class MatchExpressionRule implements RuleInterface
{
    public function name(): string { return 'tp6-match-expression'; }
    public function description(): string { return 'Convert simple switch to match expression'; }
    public function sourceVersion(): string { return 'thinkphp:6.0'; }
    public function targetVersion(): string { return 'thinkphp:8.0'; }
    public function priority(): int { return 80; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof Switch_) {
                    return null;
                }

                if ($node->cases === []) {
                    return null;
                }

                $arms = [];
                foreach ($node->cases as $case) {
                    if (!$this->isSimpleReturnCase($case)) {
                        return null;
                    }

                    $returnStmt = $this->extractReturn($case);
                    if ($returnStmt === null || $returnStmt->expr === null) {
                        return null;
                    }

                    $conds = $case->cond !== null ? [$case->cond] : null; // null = default arm
                    $arms[] = new MatchArm($conds, $returnStmt->expr);
                }

                if ($arms === []) {
                    return null;
                }

                return new Return_(new Match_($node->cond, $arms));
            }

            /**
             * A case qualifies if it has exactly 1 return statement,
             * or 1 return + nothing else meaningful (no break, no fall-through).
             */
            private function isSimpleReturnCase(Node\Stmt\Case_ $case): bool
            {
                $stmts = $case->stmts;

                // Filter out break statements (some code has return + break)
                $meaningful = array_filter($stmts, static fn($s) => !$s instanceof Break_);
                $meaningful = array_values($meaningful);

                if (count($meaningful) !== 1) {
                    return false;
                }

                return $meaningful[0] instanceof Return_ && $meaningful[0]->expr !== null;
            }

            private function extractReturn(Node\Stmt\Case_ $case): ?Return_
            {
                foreach ($case->stmts as $stmt) {
                    if ($stmt instanceof Return_) {
                        return $stmt;
                    }
                }
                return null;
            }
        };
    }
}
