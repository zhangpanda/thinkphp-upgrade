<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet\ThinkPHP;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use ThinkUpgrade\RuleSet\RuleInterface;

/**
 * C('KEY') read  → config('new.key')
 * C('KEY', $val) write → config(['new.key' => $val])
 *
 * Only transforms when the first argument is a non-empty String_ literal.
 * The second argument (write value) is preserved as-is (variable, expression, or literal).
 */
final class ConfigCallRule implements RuleInterface
{
    private const KEY_MAP = [
        // Database
        'DB_TYPE' => 'database.connections.mysql.type',
        'DB_HOST' => 'database.connections.mysql.hostname',
        'DB_NAME' => 'database.connections.mysql.database',
        'DB_USER' => 'database.connections.mysql.username',
        'DB_PWD' => 'database.connections.mysql.password',
        'DB_PORT' => 'database.connections.mysql.hostport',
        'DB_PREFIX' => 'database.connections.mysql.prefix',
        'DB_CHARSET' => 'database.connections.mysql.charset',
        // Session
        'SESSION_PREFIX' => 'session.prefix',
        'SESSION_AUTO_START' => 'session.auto_start',
        // Cache
        'CACHE_TYPE' => 'cache.default',
        'CACHE_PREFIX' => 'cache.stores.file.prefix',
        'CACHE_EXPIRE' => 'cache.stores.file.expire',
        // App
        'DEFAULT_MODULE' => 'app.default_app',
        'DEFAULT_CONTROLLER' => 'route.default_controller',
        'DEFAULT_ACTION' => 'route.default_action',
        'URL_MODEL' => 'route.url_domain_deploy',
        'PAGE_SIZE' => 'app.page_size',
        // Payment/misc often project-specific
        'DEFAULT_PAYMENT' => 'app.default_payment',
    ];

    public function name(): string { return 'tp3-config-call'; }
    public function description(): string { return 'Convert C() to config() calls'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 35; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class(self::KEY_MAP) extends NodeVisitorAbstract {
            public function __construct(private readonly array $keyMap) {}

            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof FuncCall || !$node->name instanceof Name) {
                    return null;
                }
                if ($node->name->toString() !== 'C') {
                    return null;
                }

                $arg = $node->args[0] ?? null;
                if ($arg === null || !$arg->value instanceof String_) {
                    return null;
                }

                $oldKey = $arg->value->value;
                if ($oldKey === '') {
                    return null;
                }

                $newKey = $this->keyMap[$oldKey] ?? 'app.' . strtolower($oldKey);

                // C('key', $value) → config(['new.key' => $value])
                if (isset($node->args[1])) {
                    $arrayItem = new ArrayItem(
                        $node->args[1]->value,
                        new String_($newKey),
                    );
                    return new FuncCall(
                        new Name('config'),
                        [new Arg(new Array_([$arrayItem], ['kind' => Array_::KIND_SHORT]))],
                    );
                }

                // C('key') → config('new.key')
                return new FuncCall(
                    new Name('config'),
                    [new Arg(new String_($newKey))],
                );
            }
        };
    }
}
