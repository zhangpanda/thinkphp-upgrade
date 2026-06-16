<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet;

use PhpParser\NodeVisitorAbstract;

interface RuleInterface
{
    public function name(): string;
    public function description(): string;
    public function sourceVersion(): string;
    public function targetVersion(): string;
    public function priority(): int;
    public function isAutoFixable(): bool;
    public function getTransformVisitor(): NodeVisitorAbstract;
}
