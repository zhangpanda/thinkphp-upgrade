<?php

declare(strict_types=1);

namespace PHPLift\RuleSet\ThinkPHP;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use PHPLift\RuleSet\RuleInterface;

/**
 * I('get.id', 0, 'intval') → intval($request->get('id', 0))
 * I('post.name')           → $request->post('name')
 * I('param.')              → $request->param()
 *
 * Skips: empty first arg, non-String_ args, invalid method names.
 * Filter (3rd arg) is wrapped as a function call around the result.
 */
final class InputCallRule implements RuleInterface
{
    public function name(): string { return 'tp3-input-call'; }
    public function description(): string { return 'Convert I() to Request method calls'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 38; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof FuncCall || !$node->name instanceof Name) {
                    return null;
                }
                if ($node->name->toString() !== 'I') {
                    return null;
                }

                $arg = $node->args[0] ?? null;
                if ($arg === null || !$arg->value instanceof String_) {
                    return null;
                }

                $input = $arg->value->value;
                if ($input === '') {
                    return null;
                }

                $parts = explode('.', $input, 2);
                $method = $parts[0];
                $key = $parts[1] ?? '';

                // Fallback to 'param' if method portion is empty
                if ($method === '') {
                    $method = 'param';
                }

                // Validate method is a legal PHP identifier
                if (!preg_match('/^[a-zA-Z_]\w*$/', $method)) {
                    return null;
                }

                $newArgs = [];
                if ($key !== '') {
                    $newArgs[] = new Arg(new String_($key));
                }

                // Default value (2nd arg) — preserve regardless of type
                if (isset($node->args[1])) {
                    $newArgs[] = new Arg($node->args[1]->value);
                }

                $requestCall = new MethodCall(
                    new Variable('request'),
                    new Identifier($method),
                    $newArgs,
                );

                // 3rd arg is filter function: wrap as filterFunc($request->method(...))
                if (isset($node->args[2]) && $node->args[2]->value instanceof String_) {
                    $filter = $node->args[2]->value->value;
                    if ($filter !== '' && preg_match('/^[a-zA-Z_]\w*$/', $filter)) {
                        return new FuncCall(
                            new Name($filter),
                            [new Arg($requestCall)],
                        );
                    }
                }

                return $requestCall;
            }
        };
    }
}
