# PHPLift — AI 驱动的 PHP 代码迁移工具

> 让 ThinkPHP 3.x → 6.x/8.x 迁移变得安全、可控、高效

[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-Apache%202.0-green.svg)](LICENSE)

## 解决什么问题？

国内大量 PHP 项目还在运行 ThinkPHP 3.2 + PHP 5.6，面临：
- PHP 5.6/7.x 已停止安全更新
- ThinkPHP 3.x 不再维护
- 手动升级费时费力，容易出错

PHPLift 通过 **AST 分析 + 规则驱动转换 + AI 辅助** 自动完成 80%+ 的代码迁移工作。

## 特性

- 🔍 **智能检测** — 自动识别项目框架版本和 PHP 版本
- 🔄 **7 条内置规则** — 覆盖 M/D/C/U/I 函数、控制器、命名空间
- 🤖 **AI 辅助** — 复杂场景调用 AI（支持 DeepSeek，无需翻墙）
- 📊 **迁移报告** — Console / JSON 格式报告
- 🛡️ **安全优先** — dry-run 预览 + 自动备份原文件
- 💻 **CLI 工具** — 一条命令分析、一条命令转换

## 安装

```bash
composer global require phplift/phplift
```

或在项目中安装：
```bash
composer require --dev phplift/phplift
```

## 快速使用

### 1. 分析项目

```bash
phplift analyze /var/www/old-shop
```

输出：
```
🔍 Scanning: /var/www/old-shop
📋 Framework: thinkphp 3.2.3 (confidence: 95%)
📊 PHP files: 847, Lines: 123456
  Controller/UserController.class.php: 8 issues
  Controller/OrderController.class.php: 12 issues
  ...

✅ Total issues found: 247
```

### 2. 预览转换

```bash
phplift transform /var/www/old-shop --dry-run
```

### 3. 一键迁移（推荐）

```bash
phplift migrate /var/www/old-shop --target 8.0
```

输出：
```
═══════════════════════════════════════
  PHPLift Migration Report
═══════════════════════════════════════
  Files scanned:     722
  Files changed:     600
  Syntax OK:         597
  Syntax errors:     3
  Need manual review:19
───────────────────────────────────────

Need manual review:
  ⚠️  Controller/IndexController.class.php
      → $this->success/error() needs manual replacement
      → $request variable needs injection (add Request $request parameter)

📄 Report saved to: phplift-report.json
```

`migrate` 命令会自动完成：
1. 检测源框架版本
2. 按路径分步转换代码
3. 对每个转换后的文件做 `php -l` 语法检查
4. 检测需要手动处理的模式（`$this->success`、`$request` 注入等）
5. 生成 JSON 报告

### 3. 执行转换

```bash
phplift transform /var/www/old-shop
```

转换前自动生成 `.bak` 备份文件。

## 支持的转换规则

| 规则 | TP3 代码 | TP6 代码 | 优先级 |
|------|---------|---------|--------|
| `tp3-add-namespace` | 无命名空间 | `namespace app\controller;` | 0 |
| `tp3-controller-migration` | `extends Controller` + `$this->display()` | `extends BaseController` + `View::fetch()` | 15 |
| `tp3-model-call` | `M('User')` / `D('User')` | `\app\model\User::query()` | 30 |
| `tp3-config-call` | `C('DB_HOST')` | `config('database.connections.mysql.hostname')` | 35 |
| `tp3-input-call` | `I('get.id', 0)` | `$request->get('id', 0)` | 38 |
| `tp3-url-generate` | `U('Admin/User/index')` | `url('user/index')` | 40 |
| `tp3-is-post` | `IS_POST` | `$request->isPost()` | 42 |

## 转换示例

**转换前（ThinkPHP 3.2）：**

```php
<?php
class UserController extends Controller
{
    public function index()
    {
        $list = M('User')->where('status=1')->select();
        $dbHost = C('DB_HOST');
        $this->assign('list', $list);
        $this->display();
    }

    public function edit()
    {
        $id = I('get.id', 0, 'intval');
        if (IS_POST) {
            D('User')->save(I('post.'));
            $this->success('保存成功', U('Admin/User/index'));
        }
    }
}
```

**转换后（ThinkPHP 6.0）：**

```php
<?php
namespace app\controller;

class UserController extends \think\BaseController
{
    public function index()
    {
        $list = \app\model\User::query()->where('status=1')->select();
        $dbHost = config('database.connections.mysql.hostname');
        \think\facade\View::assign('list', $list);
        \think\facade\View::fetch();
    }

    public function edit()
    {
        $id = $request->get('id', 0);
        if ($request->isPost()) {
            \app\model\User::query()->save($request->post());
            $this->success('保存成功', url('user/index'));
        }
    }
}
```

## AI 辅助

对于无法通过规则自动转换的复杂场景，PHPLift 支持调用 AI：

```bash
export DEEPSEEK_API_KEY=sk-xxx  # 国内推荐 DeepSeek
phplift transform /path/to/project --ai
```

AI 辅助场景：
- 动态变量名的 `M($modelName)` 调用
- 复杂的查询构建器组合
- 第三方扩展的适配代码
- 业务逻辑重构建议

> 💡 无 AI API Key 时基础的规则转换仍然可用，AI 只是增强。

## 项目结构

```
src/
├── Scanner/            # 项目扫描（框架/版本自动检测）
├── Analyzer/           # AST 代码分析引擎
├── Transformer/        # AST 代码转换引擎
├── RuleSet/            # 转换规则
│   └── ThinkPHP/      # ThinkPHP 专用规则（7条）
├── AI/                 # AI 辅助模块
├── Reporter/           # 报告生成（Console / JSON）
└── Console/            # CLI 命令
```

## 配置

项目根目录创建 `phplift.json`（可选）：

```json
{
    "source_version": "thinkphp:3.2",
    "target_version": "thinkphp:6.0",
    "exclude": ["vendor", "runtime", "public/static"],
    "ai": {
        "provider": "deepseek",
        "api_key": "${DEEPSEEK_API_KEY}"
    }
}
```

## 开发

```bash
# 安装依赖
composer install

# 运行测试
vendor/bin/phpunit

# 试运行
php bin/phplift analyze tests/Fixtures/tp32-sample
```

## 路线图

- [x] ThinkPHP 3.2 → 6.0 核心规则（7 条）
- [x] CLI 工具（analyze + transform）
- [x] AI 辅助模块
- [x] 报告生成
- [ ] ThinkPHP 5.x → 6.x 规则
- [ ] ThinkPHP 6.x → 8.x 规则
- [ ] Web UI（交互式确认界面）
- [ ] 模板文件迁移
- [ ] 自定义规则插件系统
- [ ] VS Code 插件

## 常见问题

**Q: 转换后代码能直接运行吗？**

A: PHPLift 处理约 80% 的自动化转换。剩余部分（路由配置、中间件注册、composer.json 依赖）需要手动调整。建议先 `--dry-run` 预览。

**Q: 支持 Laravel 迁移吗？**

A: 目前专注 ThinkPHP 生态。Laravel 迁移规则在路线图中。

**Q: 转换会破坏原文件吗？**

A: 不会。非 dry-run 模式下会自动生成 `.bak` 备份。建议在 Git 仓库中操作，方便回退。

## 参与贡献

欢迎贡献新的转换规则！参考 `src/RuleSet/ThinkPHP/ModelCallRule.php` 的实现，提交 PR 即可。

## 开源协议

[Apache 2.0](LICENSE)
