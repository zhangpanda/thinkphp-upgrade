<?php

declare(strict_types=1);

namespace ThinkUpgrade\RuleSet\ThinkPHP\Tp5ToTp6;

use PhpParser\Node;
use PhpParser\Node\Name as NodeName;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;
use ThinkUpgrade\RuleSet\RuleInterface;

/**
 * TP6 removed the module concept:
 *   app\admin\controller\User → app\controller\User
 *   app\index\model\Order     → app\model\Order
 *
 * Only transforms when:
 * - Namespace starts with "app"
 * - Has at least 3 segments (app\{module}\{layer}\...)
 * - The 3rd segment is a recognized layer (controller/model/service/middleware/validate)
 *
 * Skips 2-segment namespaces like app\controller (already correct).
 */
final class ModuleRemovalRule implements RuleInterface
{
    private const VALID_LAYERS = ['controller', 'model', 'service', 'middleware', 'validate'];

    public function name(): string { return 'tp5-module-removal'; }
    public function description(): string { return 'Remove module segment from namespace (TP6 dropped modules)'; }
    public function sourceVersion(): string { return 'thinkphp:5.1'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 5; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class(self::VALID_LAYERS) extends NodeVisitorAbstract {
            public function __construct(private readonly array $validLayers) {}

            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof Namespace_ || $node->name === null) {
                    return null;
                }

                $parts = $node->name->getParts();

                // Must be: app\{module}\{layer}[\sub...] — at least 3 segments
                if (count($parts) < 3 || $parts[0] !== 'app') {
                    return null;
                }

                // $parts[1] = module name, $parts[2] = layer
                $layer = $parts[2];
                if (!in_array($layer, $this->validLayers, true)) {
                    return null;
                }

                // Remove the module segment at index 1
                array_splice($parts, 1, 1);
                $node->name = new NodeName($parts);

                return $node;
            }
        };
    }
}
