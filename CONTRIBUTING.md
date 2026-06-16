# 贡献指南

感谢你对 ThinkPHP-Upgrade 的关注！我们特别欢迎新的迁移规则贡献。

## 开发环境

```bash
git clone https://github.com/zhangpanda/thinkphp-upgrade.git
cd thinkphp-upgrade
composer install
```

## 运行测试

```bash
vendor/bin/phpunit
```

## 如何贡献新规则

这是最有价值的贡献方式。以 `ModelCallRule` 为参考：

### 1. 创建规则类

```php
<?php
// src/RuleSet/ThinkPHP/YourRule.php

namespace ThinkUpgrade\RuleSet\ThinkPHP;

use ThinkUpgrade\RuleSet\RuleInterface;

final class YourRule implements RuleInterface
{
    public function name(): string { return 'tp3-your-rule'; }
    public function description(): string { return '描述转换内容'; }
    public function sourceVersion(): string { return 'thinkphp:3.2'; }
    public function targetVersion(): string { return 'thinkphp:6.0'; }
    public function priority(): int { return 50; } // 0-99，越小越先执行
    public function isAutoFixable(): bool { return true; }

    public function getTransformVisitor(): NodeVisitorAbstract
    {
        return new class extends NodeVisitorAbstract {
            public function leaveNode(Node $node): ?Node
            {
                // 你的 AST 转换逻辑
            }
        };
    }
}
```

### 2. 添加测试

在 `tests/` 中添加测试，用临时文件验证转换结果：

```php
public function testYourRule(): void
{
    $code = "<?php\n// 转换前代码";
    $tmp = tempnam(sys_get_temp_dir(), 'tpup_');
    file_put_contents($tmp, $code);

    $transformer = new CodeTransformer();
    $result = $transformer->transformFile($tmp, [new YourRule()], true);
    unlink($tmp);

    $this->assertStringContainsString('期望的转换结果', $result->newCode);
}
```

### 3. 注册到 MigrationPlan

如果规则属于特定迁移路径，在 `src/Engine/MigrationPlan.php` 的对应方法中添加。

### 4. 更新 MatchCollector

在 `src/Analyzer/CodeAnalyzer.php` 的 `ruleMatches()` 中添加匹配逻辑。

## 优先级约定

| 范围 | 用途 |
|------|------|
| 0-9 | 结构性变更（命名空间、文件重命名） |
| 10-29 | 类签名（继承、接口） |
| 30-49 | 函数/方法替换（M/D/C/U/I） |
| 50-69 | 配置和路由 |
| 70-89 | 代码风格现代化（PHP 8 特性） |
| 90-99 | 清理优化 |

## 提交规范

- Commit 格式：`feat(rule): 新增 xxx 规则` / `fix: 修复 xxx`
- 每个规则一个 PR，附带测试和文档说明
