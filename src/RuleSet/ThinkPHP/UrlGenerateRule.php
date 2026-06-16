<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet\ThinkPHP;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use ThinkUpgrade\RuleSet\RuleInterface;

/**
 * U('Module/Controller/action')  → url('controller/action')
 * U('Controller/action')         → url('controller/action')
 * U('action')                    → url('action')
 *
 * Only transforms String_ literals. Additional params (2nd arg) preserved.
 */
final class UrlGenerateRule implements RuleInterface
{
    public function name(): string { return 'tp3-url-generate'; }
    public function description(): string { return 'Convert U() to url() calls'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 40; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof FuncCall || !$node->name instanceof Name) {
                    return null;
                }
                if ($node->name->toString() !== 'U') {
                    return null;
                }

                $arg = $node->args[0] ?? null;
                if ($arg === null || !$arg->value instanceof String_) {
                    return null;
                }

                $oldUrl = $arg->value->value;
                if ($oldUrl === '') {
                    return null;
                }

                // Separate query string: U('User/add?id=1') → path='User/add', query='id=1'
                $query = '';
                if (str_contains($oldUrl, '?')) {
                    [$oldUrl, $query] = explode('?', $oldUrl, 2);
                }

                $parts = explode('/', $oldUrl);

                // TP3: Module/Controller/Action → controller/action (drop module)
                // TP3: Controller/Action → controller/action
                // TP3: action → action
                $newUrl = match (count($parts)) {
                    3 => strtolower($parts[1]) . '/' . $parts[2],
                    2 => strtolower($parts[0]) . '/' . $parts[1],
                    1 => strtolower($parts[0]),
                    default => strtolower($oldUrl),
                };

                if ($query !== '') {
                    $newUrl .= '?' . $query;
                }

                $newArgs = [new Arg(new String_($newUrl))];

                // Preserve additional arguments (params array, suffix, etc.)
                for ($i = 1, $count = count($node->args); $i < $count; $i++) {
                    $newArgs[] = new Arg($node->args[$i]->value);
                }

                return new FuncCall(new Name('url'), $newArgs);
            }
        };
    }
}
