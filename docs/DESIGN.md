# PHPLift — AI 驱动的 PHP 代码现代化迁移工具设计文档

## 1. 项目概览

| 项目 | 信息 |
|------|------|
| 名称 | PHPLift |
| 定位 | AI 驱动的 PHP 框架迁移与代码现代化工具 |
| 一句话 | 通过 AST 分析 + AI 辅助 + 交互式 Web UI，让 ThinkPHP 全版本迁移和 PHP 升级变得安全、可控、高效 |
| 目标用户 | 维护 ThinkPHP 3.x/5.x 老项目的开发团队、PHP 版本升级的企业、外包公司 |
| 技术要求 | PHP >= 8.1, nikic/php-parser ^5.0 |
| License | MIT |

### 为什么需要 PHPLift？

| 特性 | PHPLift | Rector | PHP-CS-Fixer |
|------|---------|--------|--------------|
| ThinkPHP 迁移 | ✅ 全版本 (TP3→TP8) | ❌ | ❌ |
| Web UI 可视化 | ✅ 交互式 diff | ❌ CLI only | ❌ |
| AI 辅助复杂转换 | ✅ 处理无法自动化的场景 | ❌ | ❌ |
| 场景化迁移路线图 | ✅ 分步引导 | ❌ 散装规则 | ❌ |
| 中文框架支持 | ✅ ThinkPHP/Yii | ❌ | ❌ |
| 学习成本 | 低（Web UI 引导） | 高（需懂 AST） | 中 |

---

## 2. 核心架构

```
┌─────────────────────────────────────────────────────────────┐
│                      接入层                                   │
│    ┌───────────────┐              ┌────────────────────┐    │
│    │     CLI       │              │      Web UI        │    │
│    │ (Symfony      │              │ (Laravel + Vue 3)  │    │
│    │  Console)     │              │                    │    │
│    └───────┬───────┘              └─────────┬──────────┘    │
├────────────┼────────────────────────────────┼───────────────┤
│            └──────────────┬─────────────────┘               │
│                    编排层  │                                  │
│            ┌──────────────┴──────────────┐                  │
│            │     MigrationEngine         │                  │
│            │  (Pipeline 编排 + 状态管理)   │                  │
│            └──────────────┬──────────────┘                  │
├───────────────────────────┼─────────────────────────────────┤
│                    核心层  │                                  │
│  ┌─────────┐  ┌──────────┴───┐  ┌─────────────┐           │
│  │Scanner  │→│  Analyzer    │→│ Transformer │           │
│  └─────────┘  └──────────────┘  └─────────────┘           │
│       ↓              ↓                  ↓                   │
│  ┌─────────┐  ┌──────────────┐  ┌─────────────┐           │
│  │RuleSet  │  │  AI Assist   │  │  Reporter   │           │
│  └─────────┘  └──────────────┘  └─────────────┘           │
├─────────────────────────────────────────────────────────────┤
│                    基础设施层                                  │
│  php-parser | Filesystem | HTTP Client | Queue | Cache      │
└─────────────────────────────────────────────────────────────┘
```

### 设计原则

1. **渐进式** — 分步迁移，每步可暂停检查，不一次性改完
2. **安全第一** — 所有变更生成 diff 预览，自动备份原文件
3. **AI 可选** — 无 API Key 时基础 AST 转换仍可用，AI 只增强
4. **大项目友好** — 支持上万文件的分片分析，异步队列处理
5. **规则可扩展** — 社区可贡献自定义规则集

---

## 3. 模块详细设计

### 3.1 Scanner — 项目扫描器

**职责**: 扫描目标项目，自动检测框架版本、PHP 版本、项目结构，输出 ProjectProfile。

```php
<?php

namespace PHPLift\Scanner;

final class ProjectScanner
{
    public function scan(string $projectPath): ProjectProfile
    {
        return new ProjectProfile(
            path: $projectPath,
            phpVersion: $this->detectPhpVersion($projectPath),
            framework: $this->detectFramework($projectPath),
            structure: $this->analyzeStructure($projectPath),
            stats: $this->collectStats($projectPath),
        );
    }

    private function detectFramework(string $path): FrameworkInfo
    {
        // 检测策略（按优先级）:
        // 1. composer.json 中的 require (topthink/framework 版本)
        // 2. 特征文件 (ThinkPHP/Conf/convention.php → TP3.2)
        // 3. 入口文件模式 (define('THINK_PATH') → TP3.x)
        // 4. 目录结构特征 (Application/ → TP3, app/ → TP5+)
    }
}

final readonly class ProjectProfile
{
    public function __construct(
        public string $path,
        public string $phpVersion,        // "5.6", "7.4", "8.1"
        public FrameworkInfo $framework,   // name + version
        public ProjectStructure $structure,
        public ProjectStats $stats,
    ) {}
}

final readonly class FrameworkInfo
{
    public function __construct(
        public string $name,      // "thinkphp", "laravel", "yii"
        public string $version,   // "3.2.3", "5.1", "6.0"
        public int $confidence,   // 检测置信度 0-100
    ) {}
}

final readonly class ProjectStats
{
    public function __construct(
        public int $totalFiles,
        public int $phpFiles,
        public int $totalLines,
        public int $classes,
        public int $functions,
    ) {}
}
```

**检测 ThinkPHP 版本的具体策略:**

```php
private function detectThinkPHPVersion(string $path): ?string
{
    // TP3.x 特征
    if (file_exists("{$path}/ThinkPHP/ThinkPHP.php")) {
        $content = file_get_contents("{$path}/ThinkPHP/ThinkPHP.php");
        if (preg_match("/THINK_VERSION.*?'(3\.\d+\.\d+)'/", $content, $m)) {
            return $m[1];
        }
    }

    // TP5.x/6.x/8.x 通过 composer.json
    $composerFile = "{$path}/composer.json";
    if (file_exists($composerFile)) {
        $composer = json_decode(file_get_contents($composerFile), true);
        $require = $composer['require']['topthink/framework'] ?? null;
        if ($require) {
            // "^6.0" → "6.0", "~5.1" → "5.1"
            return ltrim(preg_replace('/[^0-9.]/', '', $require), '.');
        }
    }

    return null;
}
```

---

### 3.2 Analyzer — 代码分析引擎

**职责**: 基于 nikic/php-parser 进行 AST 分析，匹配适用的规则，生成变更报告。

```php
<?php

namespace PHPLift\Analyzer;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPLift\RuleSet\RuleInterface;

final class CodeAnalyzer
{
    private array $rules = [];

    public function __construct(
        private readonly \PhpParser\Parser $parser,
    ) {}

    public function addRules(array $rules): void
    {
        $this->rules = $rules;
        // 按优先级排序
        usort($this->rules, fn($a, $b) => $a->priority() <=> $b->priority());
    }

    /**
     * 分析单个文件，返回需要的变更列表
     */
    public function analyzeFile(string $filePath): FileAnalysis
    {
        $code = file_get_contents($filePath);
        $ast = $this->parser->parse($code);

        $issues = [];
        foreach ($this->rules as $rule) {
            $traverser = new NodeTraverser();
            $visitor = new RuleMatchVisitor($rule);
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);
            $issues = array_merge($issues, $visitor->getMatches());
        }

        return new FileAnalysis(
            filePath: $filePath,
            issues: $issues,
            autoFixable: array_filter($issues, fn($i) => $i->autoFixable),
            needsReview: array_filter($issues, fn($i) => !$i->autoFixable && !$i->needsAI),
            needsAI: array_filter($issues, fn($i) => $i->needsAI),
        );
    }

    /**
     * 批量分析（支持大项目分片）
     */
    public function analyzeProject(ProjectProfile $profile, ?callable $onProgress = null): ProjectAnalysis
    {
        $files = $this->getPhpFiles($profile->path);
        $results = [];
        $total = count($files);

        foreach ($files as $i => $file) {
            $results[] = $this->analyzeFile($file);
            if ($onProgress) {
                $onProgress($i + 1, $total, $file);
            }
        }

        return new ProjectAnalysis($results);
    }
}

final readonly class Issue
{
    public function __construct(
        public string $ruleName,
        public string $description,
        public int $line,
        public int $column,
        public string $originalCode,
        public ?string $suggestedCode,
        public bool $autoFixable,
        public bool $needsAI,
        public IssueSeverity $severity,
    ) {}
}

enum IssueSeverity: string
{
    case Error = 'error';       // 必须修复才能运行
    case Warning = 'warning';   // 建议修复
    case Info = 'info';         // 代码风格改进
}
```

---

### 3.3 Transformer — 代码转换引擎

**职责**: 基于 AST 重写的代码转换，支持选择性应用、format-preserving。

```php
<?php

namespace PHPLift\Transformer;

use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter\Standard;

final class CodeTransformer
{
    public function __construct(
        private readonly \PhpParser\Parser $parser,
        private readonly Standard $printer,
    ) {}

    /**
     * 对单个文件应用规则转换
     */
    public function transformFile(string $filePath, array $rules, TransformOptions $options): TransformResult
    {
        $originalCode = file_get_contents($filePath);
        $ast = $this->parser->parse($originalCode);
        $originalTokens = $this->parser->getTokens();

        $applied = [];
        $skipped = [];

        foreach ($rules as $rule) {
            if ($options->shouldSkip($rule->name())) {
                $skipped[] = $rule->name();
                continue;
            }

            $traverser = new NodeTraverser();
            $visitor = $rule->getTransformVisitor();
            $traverser->addVisitor($visitor);
            $ast = $traverser->traverse($ast);

            if ($visitor->hasChanges()) {
                $applied[] = $rule->name();
            }
        }

        // Format-preserving print（尽量保持原始格式）
        $newCode = $this->printer->printFormatPreserving($ast, $originalAst, $originalTokens);

        return new TransformResult(
            filePath: $filePath,
            originalCode: $originalCode,
            newCode: $newCode,
            appliedRules: $applied,
            skippedRules: $skipped,
            diff: $this->generateDiff($originalCode, $newCode),
        );
    }

    /**
     * 批量转换（dry-run 模式不写文件）
     */
    public function transformProject(
        ProjectAnalysis $analysis,
        array $rules,
        TransformOptions $options,
    ): ProjectTransformResult {
        $results = [];

        foreach ($analysis->getFiles() as $fileAnalysis) {
            if (!$fileAnalysis->hasIssues()) continue;

            $result = $this->transformFile($fileAnalysis->filePath, $rules, $options);
            $results[] = $result;

            if (!$options->dryRun) {
                // 备份原文件
                $this->backup($fileAnalysis->filePath);
                // 写入新文件
                file_put_contents($fileAnalysis->filePath, $result->newCode);
            }
        }

        return new ProjectTransformResult($results);
    }
}

final readonly class TransformOptions
{
    public function __construct(
        public bool $dryRun = true,
        public array $skipRules = [],
        public ?string $backupDir = null,
    ) {}
}
```

---

### 3.4 RuleSet — 规则系统

**职责**: 可插拔的转换规则系统，每个规则负责一种具体的代码模式转换。

```php
<?php

namespace PHPLift\RuleSet;

use PhpParser\NodeVisitorAbstract;

interface RuleInterface
{
    /** 规则唯一名称 */
    public function name(): string;

    /** 规则描述 */
    public function description(): string;

    /** 适用的源框架版本 */
    public function sourceVersion(): string; // e.g. "thinkphp:3.2"

    /** 目标框架版本 */
    public function targetVersion(): string; // e.g. "thinkphp:6.0"

    /** 优先级 (0=最高, 99=最低) */
    public function priority(): int;

    /** 返回用于检测匹配的 Visitor */
    public function getMatchVisitor(): NodeVisitorAbstract;

    /** 返回用于执行转换的 Visitor */
    public function getTransformVisitor(): NodeVisitorAbstract;

    /** 此规则是否能完全自动转换 */
    public function isAutoFixable(): bool;
}

// 规则优先级分组:
// 0-9:   结构性变更 (添加命名空间、文件重命名)
// 10-29: 类签名变更 (继承关系、接口实现)
// 30-49: 函数/方法调用替换 (M() → Model::, C() → config())
// 50-69: 配置和路由迁移
// 70-89: 代码风格现代化
// 90-99: 清理和优化

// 规则集加载器
final class RuleSetLoader
{
    public function loadForMigration(string $from, string $to): array
    {
        // 根据源版本和目标版本自动选择适用的规则集
        // 例: thinkphp:3.2 → thinkphp:6.0 会加载:
        //   - Tp3ToTp5 规则集
        //   - Tp5ToTp6 规则集
        //   - PHP 版本升级规则集
    }
}
```

**具体规则实现示例:**

#### 规则 1: M() → Model 类调用

```php
<?php

namespace PHPLift\RuleSet\ThinkPHP;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitorAbstract;
use PHPLift\RuleSet\RuleInterface;

/**
 * 将 TP3 的 M('User') 转换为 TP6 的 \app\model\User::class 静态调用
 *
 * Before: M('User')->where('status', 1)->select()
 * After:  \app\model\User::where('status', 1)->select()
 */
final class ModelCallRule implements RuleInterface
{
    public function name(): string { return 'tp3-model-call'; }
    public function description(): string { return '将 M()/D() 快捷函数转换为模型类静态调用'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 30; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            private bool $changed = false;

            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof FuncCall) return null;
                if (!$node->name instanceof Node\Name) return null;

                $funcName = $node->name->toString();
                if ($funcName !== 'M' && $funcName !== 'D') return null;

                // 获取模型名参数
                $arg = $node->args[0] ?? null;
                if (!$arg || !$arg->value instanceof Node\Scalar\String_) return null;

                $modelName = $arg->value->value; // e.g. "User"
                $fqcn = "\\app\\model\\{$modelName}";

                $this->changed = true;

                // 替换为 \app\model\User::class 的静态调用入口
                return new Node\Expr\New_(
                    new Node\Name\FullyQualified("app\\model\\{$modelName}")
                );
            }

            public function hasChanges(): bool { return $this->changed; }
        };
    }

    public function getMatchVisitor(): NodeVisitorAbstract { /* 类似但只收集不修改 */ }
}
```

#### 规则 2: C() → config()

```php
<?php

namespace PHPLift\RuleSet\ThinkPHP;

/**
 * 将 TP3 的 C('DB_HOST') 转换为 TP6 的 config('database.connections.mysql.hostname')
 *
 * Before: C('DB_HOST')
 * After:  config('database.connections.mysql.hostname')
 *
 * Before: C('URL_MODEL')
 * After:  config('route.url_common_param')
 */
final class ConfigCallRule implements RuleInterface
{
    // TP3 配置键 → TP6 配置路径映射表
    private const KEY_MAP = [
        'DB_TYPE'       => 'database.connections.mysql.type',
        'DB_HOST'       => 'database.connections.mysql.hostname',
        'DB_NAME'       => 'database.connections.mysql.database',
        'DB_USER'       => 'database.connections.mysql.username',
        'DB_PWD'        => 'database.connections.mysql.password',
        'DB_PORT'       => 'database.connections.mysql.hostport',
        'DB_PREFIX'     => 'database.connections.mysql.prefix',
        'DB_CHARSET'    => 'database.connections.mysql.charset',
        'URL_MODEL'     => 'route.url_common_param',
        'DEFAULT_MODULE'=> 'route.default_module', // TP6 已移除模块概念
        'SESSION_AUTO_START' => 'session.auto_start',
        'TMPL_PARSE_STRING'  => 'view.tpl_replace_string',
    ];

    public function name(): string { return 'tp3-config-call'; }
    public function priority(): int { return 35; }
    public function isAutoFixable(): bool { return true; } // 映射表内的可自动

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class(self::KEY_MAP) extends NodeVisitorAbstract {
            private bool $changed = false;
            public function __construct(private array $keyMap) {}

            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof FuncCall) return null;
                if ($node->name->toString() !== 'C') return null;

                $arg = $node->args[0] ?? null;
                if (!$arg || !$arg->value instanceof Node\Scalar\String_) return null;

                $oldKey = $arg->value->value;
                $newKey = $this->keyMap[$oldKey] ?? null;

                if (!$newKey) {
                    // 未知键名，转为小写 snake_case 作为 fallback
                    $newKey = 'app.' . strtolower($oldKey);
                }

                $this->changed = true;

                return new FuncCall(
                    new Node\Name('config'),
                    [new Node\Arg(new Node\Scalar\String_($newKey))]
                );
            }

            public function hasChanges(): bool { return $this->changed; }
        };
    }
}
```

#### 规则 3: U() → url()

```php
<?php

namespace PHPLift\RuleSet\ThinkPHP;

/**
 * 将 TP3 的 U('Module/Controller/action') 转换为 TP6 的 url('controller/action')
 * TP6 移除了模块概念，需要去掉第一段
 *
 * Before: U('Admin/User/index')
 * After:  url('user/index')
 *
 * Before: U('User/edit', ['id' => 1])
 * After:  url('user/edit', ['id' => 1])
 */
final class UrlGenerateRule implements RuleInterface
{
    public function name(): string { return 'tp3-url-generate'; }
    public function priority(): int { return 40; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            private bool $changed = false;

            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof FuncCall) return null;
                if ($node->name->toString() !== 'U') return null;

                $args = $node->args;
                $urlArg = $args[0] ?? null;
                if (!$urlArg || !$urlArg->value instanceof Node\Scalar\String_) return null;

                $oldUrl = $urlArg->value->value;
                $parts = explode('/', $oldUrl);

                // TP3: Module/Controller/Action → TP6: controller/action
                if (count($parts) === 3) {
                    // 去掉模块，controller 转小写
                    $newUrl = strtolower($parts[1]) . '/' . $parts[2];
                } elseif (count($parts) === 2) {
                    $newUrl = strtolower($parts[0]) . '/' . $parts[1];
                } else {
                    $newUrl = strtolower($oldUrl);
                }

                $this->changed = true;

                $newArgs = [new Node\Arg(new Node\Scalar\String_($newUrl))];
                // 保留第二个参数（URL 参数数组）
                if (isset($args[1])) {
                    $newArgs[] = $args[1];
                }

                return new FuncCall(new Node\Name('url'), $newArgs);
            }

            public function hasChanges(): bool { return $this->changed; }
        };
    }
}
```

#### 规则 4: 添加命名空间

```php
<?php

namespace PHPLift\RuleSet\ThinkPHP;

/**
 * 为 TP3 无命名空间的类添加命名空间
 * 根据文件路径推断命名空间
 *
 * Before: class UserController extends Controller { ... }
 * After:  namespace app\controller;
 *         class UserController extends \think\BaseController { ... }
 */
final class AddNamespaceRule implements RuleInterface
{
    public function name(): string { return 'tp3-add-namespace'; }
    public function priority(): int { return 0; } // 最高优先级 — 结构性变更
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            private bool $changed = false;
            private string $filePath = '';

            public function setFilePath(string $path): void
            {
                $this->filePath = $path;
            }

            public function beforeTraverse(array $nodes): ?array
            {
                // 检查是否已有命名空间
                foreach ($nodes as $node) {
                    if ($node instanceof Node\Stmt\Namespace_) {
                        return null; // 已有命名空间，跳过
                    }
                }

                // 根据文件路径推断命名空间
                // Application/Admin/Controller/UserController.class.php
                // → app\admin\controller
                $namespace = $this->inferNamespace($this->filePath);
                if (!$namespace) return null;

                $this->changed = true;

                // 包裹在 namespace 语句中
                return [new Node\Stmt\Namespace_(
                    new Node\Name($namespace),
                    $nodes
                )];
            }

            private function inferNamespace(string $path): ?string
            {
                // Application/模块/Controller/ → app\模块\controller
                if (preg_match('#Application/(\w+)/Controller/#', $path, $m)) {
                    return 'app\\' . strtolower($m[1]) . '\\controller';
                }
                if (preg_match('#Application/(\w+)/Model/#', $path, $m)) {
                    return 'app\\' . strtolower($m[1]) . '\\model';
                }
                return null;
            }

            public function hasChanges(): bool { return $this->changed; }
        };
    }
}
```

#### 规则 5: 控制器继承变更

```php
<?php

namespace PHPLift\RuleSet\ThinkPHP;

/**
 * TP3: class UserController extends Controller
 * TP6: class UserController extends BaseController
 *
 * 同时处理 $this->display() → return View::fetch()
 * 和 $this->assign() → View::assign()
 */
final class ControllerMigrationRule implements RuleInterface
{
    public function name(): string { return 'tp3-controller-migration'; }
    public function priority(): int { return 15; }
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            private bool $changed = false;

            public function leaveNode(Node $node): ?Node
            {
                // 1. 修改继承: Controller → BaseController
                if ($node instanceof Node\Stmt\Class_) {
                    if ($node->extends && $node->extends->toString() === 'Controller') {
                        $node->extends = new Node\Name\FullyQualified('think\\BaseController');
                        $this->changed = true;
                    }
                }

                // 2. $this->display() → return View::fetch()
                if ($node instanceof Node\Expr\MethodCall
                    && $node->var instanceof Node\Expr\Variable
                    && $node->var->name === 'this'
                    && $node->name instanceof Node\Identifier
                    && $node->name->name === 'display') {

                    $this->changed = true;
                    $args = $node->args;
                    return new StaticCall(
                        new Node\Name\FullyQualified('think\\facade\\View'),
                        'fetch',
                        $args
                    );
                }

                // 3. $this->assign('key', $value) → View::assign('key', $value)
                if ($node instanceof Node\Expr\MethodCall
                    && $node->var instanceof Node\Expr\Variable
                    && $node->var->name === 'this'
                    && $node->name instanceof Node\Identifier
                    && $node->name->name === 'assign') {

                    $this->changed = true;
                    return new StaticCall(
                        new Node\Name\FullyQualified('think\\facade\\View'),
                        'assign',
                        $node->args
                    );
                }

                return null;
            }

            public function hasChanges(): bool { return $this->changed; }
        };
    }
}
```

#### 规则 6: 文件重命名 (.class.php → .php)

```php
<?php

namespace PHPLift\RuleSet\ThinkPHP;

/**
 * TP3 使用 .class.php 后缀:
 *   UserModel.class.php → User.php (移到 app/model/)
 *   UserController.class.php → User.php (移到 app/controller/)
 */
final class FileRenameRule implements RuleInterface
{
    public function name(): string { return 'tp3-file-rename'; }
    public function priority(): int { return 1; } // 高优先级，先重命名再转换内容
    public function isAutoFixable(): bool { return true; }

    /** 此规则特殊 — 操作的是文件系统而非 AST */
    public function getFileOperations(string $filePath): ?FileRenameOperation
    {
        if (!str_ends_with($filePath, '.class.php')) {
            return null;
        }

        $basename = basename($filePath, '.class.php');

        // 去掉 Controller/Model 后缀（TP6 通过目录区分）
        $newName = preg_replace('/(Controller|Model)$/', '', $basename);

        // 推断目标目录
        if (str_contains($filePath, '/Controller/')) {
            $targetDir = 'app/controller/';
        } elseif (str_contains($filePath, '/Model/')) {
            $targetDir = 'app/model/';
        } else {
            $targetDir = dirname($filePath) . '/';
        }

        return new FileRenameOperation(
            from: $filePath,
            to: $targetDir . $newName . '.php',
        );
    }
}
```

---

### 3.5 AI Assist — AI 辅助模块

**职责**: 处理无法通过规则自动化转换的复杂场景，用 LLM 生成建议代码。

```php
<?php

namespace PHPLift\AI;

interface AIProviderInterface
{
    public function suggest(MigrationContext $context): AISuggestion;
    public function isAvailable(): bool;
}

final class MigrationContext
{
    public function __construct(
        public readonly string $originalCode,
        public readonly string $filePath,
        public readonly string $fromFramework,
        public readonly string $toFramework,
        public readonly string $ruleName,
        public readonly ?string $surroundingCode = null,
        public readonly ?string $errorMessage = null,
    ) {}
}

final readonly class AISuggestion
{
    public function __construct(
        public string $suggestedCode,
        public string $explanation,
        public float $confidence,   // 0.0 - 1.0
        public bool $needsReview,
    ) {}
}

final class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $model = 'gpt-4o',
    ) {}

    public function suggest(MigrationContext $context): AISuggestion
    {
        $prompt = $this->buildPrompt($context);
        // 调用 OpenAI API
        // 解析响应
        // 验证生成的代码语法
    }

    private function buildPrompt(MigrationContext $context): string
    {
        return <<<PROMPT
        你是一个 PHP 代码迁移专家。请将以下代码从 {$context->fromFramework} 迁移到 {$context->toFramework}。

        ## 迁移规则
        - 保持业务逻辑不变
        - 使用目标框架的最佳实践
        - 保持代码可读性

        ## 原始代码
        文件: {$context->filePath}
        ```php
        {$context->originalCode}
        ```

        ## 上下文代码
        ```php
        {$context->surroundingCode}
        ```

        ## 要求
        请只输出迁移后的 PHP 代码，不要包含解释。使用 ```php 代码块包裹。
        PROMPT;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }
}

// 降级策略
final class AIAssistManager
{
    private array $providers = [];

    public function addProvider(AIProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    public function suggest(MigrationContext $context): AISuggestion
    {
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                $suggestion = $provider->suggest($context);
                // 语法验证
                if ($this->validateSyntax($suggestion->suggestedCode)) {
                    return $suggestion;
                }
            }
        }

        // 所有 AI 都不可用时，返回手动处理标记
        return new AISuggestion(
            suggestedCode: $context->originalCode,
            explanation: '需要手动迁移。AI 辅助不可用，请参考目标框架文档进行修改。',
            confidence: 0.0,
            needsReview: true,
        );
    }

    private function validateSyntax(string $code): bool
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        try {
            $parser->parse("<?php\n" . $code);
            return true;
        } catch (\PhpParser\Error) {
            return false;
        }
    }
}
```

**AI 使用场景:**

| 场景 | 示例 | 为什么需要 AI |
|------|------|-------------|
| 动态模型名 | `M($modelName)` 变量调用 | 无法静态推断类名 |
| 复杂查询构建器 | TP3 连贯操作混用 | 语法差异大且组合复杂 |
| 业务逻辑耦合框架 | 直接使用 `$_GET`/`$_POST` | 需要理解上下文重构 |
| 模板语法迁移 | `<volist>` → `{foreach}` | 模板不是 PHP AST |
| 第三方扩展适配 | TP3 行为/钩子 → TP6 事件 | 需要理解设计意图 |

---

### 3.6 Reporter — 报告生成器

**职责**: 生成可视化的迁移报告，支持 Console、HTML、JSON 三种格式。

```php
<?php

namespace PHPLift\Reporter;

interface ReporterInterface
{
    public function generate(ProjectAnalysis $analysis): string;
}

final class ConsoleReporter implements ReporterInterface
{
    public function generate(ProjectAnalysis $analysis): string
    {
        // 输出彩色终端表格
        // ✅ 可自动修复: 892 处
        // ⚠️  需确认: 284 处
        // ❌ 需手动/AI: 71 处
    }
}

final class HtmlReporter implements ReporterInterface
{
    public function generate(ProjectAnalysis $analysis): string
    {
        // 生成包含统计图表、文件列表、diff 预览的 HTML 报告
        // 可用浏览器打开离线查看
    }
}

final class JsonReporter implements ReporterInterface
{
    public function generate(ProjectAnalysis $analysis): string
    {
        // 机器可读的 JSON 报告，供 CI/CD 集成
    }
}
```

---

### 3.7 Web UI — 交互式界面

**职责**: 提供可视化的项目导入、分析预览、交互确认、执行监控界面。

**技术栈:**
- 后端: Laravel 11 (轻量使用，主要用路由+队列+WebSocket)
- 前端: Vue 3 + Vite + Arco Design
- Diff 展示: Monaco Editor (VS Code 内核)
- 实时通信: Laravel Reverb (WebSocket)
- 队列: Redis + Laravel Queue (大项目异步分析)

**页面流程:**

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ 1. 导入  │───→│ 2. 配置  │───→│ 3. 分析  │───→│ 4. 确认  │───→│ 5. 完成  │
│   项目   │    │  目标版本 │    │  预览    │    │  执行    │    │  报告    │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
```

```
Step 1 — 项目导入
┌─────────────────────────────────────────────────────┐
│  📁 选择项目目录                                     │
│  [/var/www/old-shop                          ] [扫描]│
│                                                     │
│  ─── 扫描结果 ───                                    │
│  框架: ThinkPHP 3.2.3  (置信度: 95%)                 │
│  PHP 版本: 5.6                                      │
│  文件数: 847 个 PHP 文件                             │
│  代码行数: 123,456 行                                │
│  控制器: 42 个                                       │
│  模型: 38 个                                        │
│                                     [下一步 →]      │
└─────────────────────────────────────────────────────┘

Step 3 — 分析预览
┌─────────────────────────────────────────────────────┐
│  📊 分析完成                                         │
│                                                     │
│  ┌─────────────────────────────────────────┐       │
│  │  🟢 自动修复   ████████████████  892     │       │
│  │  🟡 需确认     ███████           284     │       │
│  │  🔴 需AI/手动  ██                 71     │       │
│  └─────────────────────────────────────────┘       │
│                                                     │
│  按规则分组:                                         │
│  ├─ tp3-model-call (M/D 函数): 234 处               │
│  ├─ tp3-config-call (C 函数): 156 处                │
│  ├─ tp3-url-generate (U 函数): 89 处                │
│  ├─ tp3-add-namespace: 67 处                        │
│  ├─ tp3-controller-migration: 42 处                 │
│  └─ ... 更多                                        │
│                                                     │
│  [查看详细文件列表] [导出报告] [开始迁移 →]           │
└─────────────────────────────────────────────────────┘

Step 4 — 交互确认（Monaco Diff Editor）
┌─────────────────────────────────────────────────────┐
│  文件: app/Controller/UserController.class.php       │
│  规则: tp3-controller-migration                      │
│  ┌────────────────────┬─────────────────────────┐   │
│  │ // 旧代码          │ // 新代码               │   │
│  │ namespace           │ namespace               │   │
│  │   app\admin\       │   app\controller;       │   │
│  │   controller;      │                         │   │
│  │                    │ use think\BaseController;│   │
│  │ class User extends │ class User extends      │   │
│  │   Controller {     │   BaseController {      │   │
│  │   public function  │   public function       │   │
│  │     index() {      │     index() {           │   │
│  │     $list = M('User')│   $list = User::     │   │
│  │       ->where(...)  │     ->where(...)       │   │
│  │       ->select();  │     ->select();         │   │
│  │     $this->assign( │   View::assign(         │   │
│  │       'list',$list)│     'list', $list);     │   │
│  │     $this->display()│  return View::fetch(); │   │
│  │   }                │   }                     │   │
│  └────────────────────┴─────────────────────────┘   │
│                                                     │
│  [✓ 接受] [✗ 跳过] [✎ 手动编辑] [🤖 AI建议]         │
│                                                     │
│  进度: 15/284 需确认项  ████░░░░░░░  5%             │
└─────────────────────────────────────────────────────┘
```

---

### 3.8 CLI — 命令行接口

**职责**: 提供纯命令行的分析和转换能力，适合 CI/CD 集成。

```php
<?php

namespace PHPLift\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription('分析项目，生成迁移报告')
            ->addArgument('path', InputArgument::REQUIRED, '项目路径')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, '目标框架版本', 'thinkphp:6.0')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, '报告格式 (console|html|json)', 'console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $target = $input->getOption('target');

        $output->writeln("🔍 扫描项目: {$path}");
        $profile = $this->scanner->scan($path);

        $output->writeln("📋 检测到: {$profile->framework->name} {$profile->framework->version}");
        $output->writeln("📊 PHP 文件: {$profile->stats->phpFiles} 个");

        $output->writeln("\n⏳ 分析中...");
        $analysis = $this->analyzer->analyzeProject($profile, function ($current, $total) use ($output) {
            // 进度条
        });

        $report = $this->reporter->generate($analysis);
        $output->writeln($report);

        return Command::SUCCESS;
    }
}

final class TransformCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('transform')
            ->setDescription('执行代码转换')
            ->addArgument('path', InputArgument::REQUIRED)
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, '目标版本', 'thinkphp:6.0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '预览模式，不实际修改文件')
            ->addOption('ai', null, InputOption::VALUE_NONE, '启用 AI 辅助')
            ->addOption('backup', 'b', InputOption::VALUE_REQUIRED, '备份目录');
    }
}
```

**CLI 使用流程:**

```bash
# 1. 安装
git clone git@github.com:zhangpanda/phplift.git && cd phplift && composer install

# 2. 分析项目
phplift analyze /var/www/old-shop --target thinkphp:6.0

# 输出:
# 🔍 扫描项目: /var/www/old-shop
# 📋 检测到: ThinkPHP 3.2.3
# 📊 PHP 文件: 847 个, 代码行: 123,456
#
# ⏳ 分析中... ████████████████████ 100%
#
# ═══════════════════════════════════════
#  分析报告: ThinkPHP 3.2 → ThinkPHP 6.0
# ═══════════════════════════════════════
#  🟢 可自动修复:  892 处
#  🟡 需人工确认:  284 处
#  🔴 需AI/手动:    71 处
# ───────────────────────────────────────
#  详细规则:
#  tp3-model-call       234 处  [auto]
#  tp3-config-call      156 处  [auto]
#  tp3-url-generate      89 处  [auto]
#  tp3-add-namespace     67 处  [auto]
#  ...

# 3. 预览转换结果
phplift transform /var/www/old-shop --target thinkphp:6.0 --dry-run

# 4. 执行转换（带备份）
phplift transform /var/www/old-shop --target thinkphp:6.0 --backup ./backup/

# 5. 启用 AI 辅助处理复杂场景
export OPENAI_API_KEY=sk-xxx
phplift transform /var/www/old-shop --target thinkphp:6.0 --ai
```

---

## 4. 目录结构

```
phplift/
├── bin/
│   └── phplift                     # CLI 入口
├── src/
│   ├── Scanner/
│   │   ├── ProjectScanner.php
│   │   ├── ProjectProfile.php
│   │   ├── FrameworkInfo.php
│   │   ├── ProjectStructure.php
│   │   └── ProjectStats.php
│   ├── Analyzer/
│   │   ├── CodeAnalyzer.php
│   │   ├── FileAnalysis.php
│   │   ├── ProjectAnalysis.php
│   │   ├── Issue.php
│   │   └── RuleMatchVisitor.php
│   ├── Transformer/
│   │   ├── CodeTransformer.php
│   │   ├── TransformResult.php
│   │   ├── TransformOptions.php
│   │   └── ProjectTransformResult.php
│   ├── RuleSet/
│   │   ├── RuleInterface.php
│   │   ├── RuleSetLoader.php
│   │   ├── PHP/
│   │   │   ├── Php56ToPhp74Rule.php
│   │   │   ├── Php74ToPhp80Rule.php
│   │   │   └── Php80ToPhp83Rule.php
│   │   └── ThinkPHP/
│   │       ├── ModelCallRule.php
│   │       ├── ConfigCallRule.php
│   │       ├── UrlGenerateRule.php
│   │       ├── AddNamespaceRule.php
│   │       ├── ControllerMigrationRule.php
│   │       └── FileRenameRule.php
│   ├── AI/
│   │   ├── AIProviderInterface.php
│   │   ├── AIAssistManager.php
│   │   ├── MigrationContext.php
│   │   ├── AISuggestion.php
│   │   └── Provider/
│   │       ├── OpenAIProvider.php
│   │       └── DeepSeekProvider.php
│   ├── Reporter/
│   │   ├── ReporterInterface.php
│   │   ├── ConsoleReporter.php
│   │   ├── HtmlReporter.php
│   │   └── JsonReporter.php
│   ├── Console/
│   │   ├── AnalyzeCommand.php
│   │   ├── TransformCommand.php
│   │   └── InitCommand.php
│   └── Engine/
│       ├── MigrationEngine.php      # 总编排
│       └── MigrationPlan.php        # 迁移计划（路线图）
├── web/                             # Web UI (独立 Laravel 应用)
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── ProjectController.php
│   │   │   ├── AnalysisController.php
│   │   │   └── TransformController.php
│   │   ├── Jobs/
│   │   │   ├── AnalyzeProjectJob.php
│   │   │   └── TransformProjectJob.php
│   │   └── Events/
│   │       └── AnalysisProgress.php
│   ├── resources/
│   │   └── js/                      # Vue 3 前端
│   │       ├── pages/
│   │       │   ├── Import.vue
│   │       │   ├── Configure.vue
│   │       │   ├── Analysis.vue
│   │       │   ├── Confirm.vue
│   │       │   └── Report.vue
│   │       └── components/
│   │           ├── DiffViewer.vue
│   │           ├── ProgressBar.vue
│   │           └── RuleList.vue
│   └── routes/
│       ├── api.php
│       └── web.php
├── tests/
│   ├── Unit/
│   │   ├── Scanner/
│   │   ├── RuleSet/
│   │   └── Transformer/
│   ├── Integration/
│   └── Fixtures/                    # 测试用 ThinkPHP 3.x 代码样本
│       ├── tp32-sample/
│       └── tp51-sample/
├── config/
│   ├── phplift.php                  # 默认配置
│   └── rules.php                    # 规则配置
├── composer.json
├── phpunit.xml.dist
└── README.md
```

---

## 5. 依赖关系

```json
{
    "name": "phplift/phplift",
    "description": "AI-powered PHP code modernization and framework migration tool",
    "bin": ["bin/phplift"],
    "require": {
        "php": "^8.1",
        "nikic/php-parser": "^5.0",
        "symfony/console": "^7.0",
        "symfony/filesystem": "^7.0",
        "symfony/finder": "^7.0",
        "sebastian/diff": "^6.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^1.12"
    },
    "suggest": {
        "guzzlehttp/guzzle": "For AI assist feature (OpenAI/DeepSeek API calls)",
        "laravel/framework": "For Web UI mode"
    },
    "autoload": {
        "psr-4": {
            "PHPLift\\": "src/"
        }
    }
}
```

---

## 6. MVP 路线图

### Phase 1 — 核心引擎（Week 1-4）

| 任务 | 说明 |
|------|------|
| Scanner | 项目扫描 + ThinkPHP 版本检测 |
| Analyzer | AST 分析引擎 + RuleMatchVisitor |
| Transformer | AST 重写 + format-preserving printing |
| 基础规则集 | TP3→TP6 的 6 个核心规则 |
| CLI | analyze + transform 命令 |
| Reporter | Console + JSON 格式输出 |
| 测试 | 每个规则的 unit test + fixture |

**Phase 1 交付物**: 可通过 CLI 对 ThinkPHP 3.2 项目进行基础分析和自动转换。

### Phase 2 — AI + Web UI（Week 5-8）

| 任务 | 说明 |
|------|------|
| AI Assist | OpenAI/DeepSeek 集成 + 降级策略 |
| Web UI 后端 | Laravel 项目 + API 路由 + 队列 |
| Web UI 前端 | Vue 3 + 5 个页面 + Monaco Diff |
| WebSocket | 实时分析进度推送 |
| HTML Reporter | 离线可查看的 HTML 报告 |
| 更多规则 | TP5→TP6, PHP 7.x→8.x 规则 |

**Phase 2 交付物**: 完整的 Web UI 交互式迁移体验 + AI 辅助。

### Phase 3 — 完善 + 发布（Week 9-12）

| 任务 | 说明 |
|------|------|
| 全路径支持 | TP3→TP5→TP6→TP8 完整迁移路径 |
| 插件系统 | 社区可贡献自定义规则 |
| Laravel 规则集 | Laravel 8→11 迁移规则 |
| 文档站 | VitePress 文档 + 视频教程 |
| 国际化 | 中/英双语界面和报告 |
| CI/CD 集成 | GitHub Action 插件 |

---

## 7. 完整转换示例

### ThinkPHP 3.2 → ThinkPHP 6.0 — UserController

**转换前 (TP3.2):**

```php
<?php
// Application/Admin/Controller/UserController.class.php

class UserController extends Controller
{
    public function index()
    {
        $User = M('User');
        $list = $User->where('status=1')
            ->order('create_time desc')
            ->limit(20)
            ->select();

        $count = $User->where('status=1')->count();

        $this->assign('list', $list);
        $this->assign('count', $count);
        $this->display();
    }

    public function edit()
    {
        $id = I('get.id', 0, 'intval');
        if (IS_POST) {
            $data = I('post.');
            $User = D('User');
            if ($User->create($data)) {
                $User->save();
                $this->success('修改成功', U('Admin/User/index'));
            } else {
                $this->error($User->getError());
            }
        } else {
            $info = M('User')->find($id);
            $this->assign('info', $info);
            $this->display();
        }
    }

    public function delete()
    {
        $id = I('get.id', 0, 'intval');
        if (M('User')->delete($id)) {
            $this->success('删除成功', U('Admin/User/index'));
        } else {
            $this->error('删除失败');
        }
    }
}
```

**转换后 (TP6.0):**

```php
<?php
// app/controller/User.php

namespace app\controller;

use app\model\User as UserModel;
use think\BaseController;
use think\facade\View;
use think\Request;

class User extends BaseController
{
    public function index()
    {
        $list = UserModel::where('status', 1)
            ->order('create_time', 'desc')
            ->limit(20)
            ->select();

        $count = UserModel::where('status', 1)->count();

        View::assign('list', $list);
        View::assign('count', $count);
        return View::fetch();
    }

    public function edit(Request $request)
    {
        $id = $request->get('id/d', 0);
        if ($request->isPost()) {
            $data = $request->post();
            $user = new UserModel();
            if ($user->save($data)) {
                return redirect(url('user/index'))->with('msg', '修改成功');
            } else {
                return redirect()->back()->with('error', '修改失败');
            }
        } else {
            $info = UserModel::find($id);
            View::assign('info', $info);
            return View::fetch();
        }
    }

    public function delete(Request $request)
    {
        $id = $request->get('id/d', 0);
        if (UserModel::destroy($id)) {
            return redirect(url('user/index'))->with('msg', '删除成功');
        } else {
            return redirect()->back()->with('error', '删除失败');
        }
    }
}
```

**变更摘要 (PHPLift 报告):**

| # | 规则 | 变更 | 模式 |
|---|------|------|------|
| 1 | tp3-file-rename | `UserController.class.php` → `User.php` | 🟢 自动 |
| 2 | tp3-add-namespace | 添加 `namespace app\controller` | 🟢 自动 |
| 3 | tp3-controller-migration | `Controller` → `BaseController` | 🟢 自动 |
| 4 | tp3-model-call | `M('User')` → `UserModel::` | 🟢 自动 |
| 5 | tp3-model-call | `D('User')` → `new UserModel()` | 🟢 自动 |
| 6 | tp3-config-call | `I('get.id')` → `$request->get()` | 🟡 需确认 |
| 7 | tp3-url-generate | `U('Admin/User/index')` → `url('user/index')` | 🟢 自动 |
| 8 | tp3-controller-migration | `$this->display()` → `View::fetch()` | 🟢 自动 |
| 9 | tp3-controller-migration | `$this->assign()` → `View::assign()` | 🟢 自动 |
| 10 | tp3-controller-migration | `IS_POST` → `$request->isPost()` | 🟢 自动 |
| 11 | tp3-controller-migration | `$this->success/error()` → redirect | 🔴 AI辅助 |
| 12 | tp3-query-syntax | `where('status=1')` → `where('status', 1)` | 🟡 需确认 |

---

## 8. 后续扩展方向

- **Yii 1→Yii 2 迁移**: 类似 ThinkPHP 的场景
- **CodeIgniter 3→4 迁移**: 国外用户需求
- **模板迁移引擎**: 独立的视图模板转换 (Smarty → Blade 等)
- **数据库迁移**: Schema diff → 自动生成 migration 文件
- **CI/CD 守卫**: PR 中自动检查代码是否符合目标框架最佳实践
- **VS Code 插件**: 编辑器内实时提示可升级的代码
