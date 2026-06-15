# Web UI 使用指南

PHPLift 内置交互式 Web UI，让你逐条审查、确认或跳过每个代码变更。

## 启动

```bash
phplift serve /var/www/old-shop --target 8.0
```

选项：
- `--port` / `-p` — 端口号（默认 8190）
- `--target` / `-t` — 目标版本（默认 8.0）

启动后浏览器打开 `http://localhost:8190`。

## 界面功能

### 顶部状态栏
- 显示源框架版本和目标版本
- 实时统计：待确认 / 已确认 / 已跳过数量
- 进度条

### 变更卡片
每个代码变更以卡片形式展示：
- 文件路径
- 应用的规则标签
- Diff 视图（上下文折叠，只展示变化行）
- 操作按钮：✓ 接受 / ✗ 跳过

### 过滤器
- 待确认 — 未处理的变更
- 已确认 — 已接受的变更
- 已跳过 — 已跳过的变更
- 全部

### 批量操作
点击「全部接受并应用」会：
1. 将所有 pending 状态改为 confirmed
2. 为每个原文件创建 `.bak` 备份
3. 写入转换后的代码

## API 端点

| 端点 | 方法 | 说明 |
|------|------|------|
| `/api/project` | GET | 项目概况 + 统计 |
| `/api/changes?status=pending` | GET | 获取变更列表 |
| `/api/changes/confirm` | POST | 确认单个变更 `{id}` |
| `/api/changes/skip` | POST | 跳过单个变更 `{id}` |
| `/api/changes/confirm-all` | POST | 全部确认并写入 |

## 注意事项

- 首次加载 `/api/changes` 时会执行全量 dry-run 转换，项目文件多时需等待
- 状态存储在系统临时目录（`/tmp/phplift_xxx.json`），关闭服务后重启可恢复
- 写入操作不可撤销（但有 .bak 文件），建议在 Git 仓库中使用

# 模板文件迁移

## 概述

PHPLift 支持 ThinkPHP 3.x 模板语法（XML 标签风格）自动转换为 ThinkPHP 6.x/8.x 的花括号语法。

## 支持的转换

| TP3 标签 | TP6 语法 | 说明 |
|----------|----------|------|
| `<volist name="list" id="vo">` | `{volist name="list" id="vo"}` | 循环 |
| `</volist>` | `{/volist}` | |
| `<foreach name="items" item="vo">` | `{foreach name="items" item="vo"}` | foreach |
| `<if condition="...">` | `{if condition="..."}` | 条件 |
| `<elseif condition="..."/>` | `{elseif condition="..." /}` | |
| `<else/>` | `{else /}` | |
| `</if>` | `{/if}` | |
| `<eq name="x" value="y">` | `{eq name="x" value="y"}` | 等值判断 |
| `<neq name="x" value="y">` | `{neq name="x" value="y"}` | 不等判断 |
| `<empty name="x">` | `{empty name="x"}` | 空值判断 |
| `<notempty name="x">` | `{notempty name="x"}` | 非空判断 |
| `<include file="..." />` | `{include file="..." /}` | 包含 |
| `__URL__` | `{:url('/')}` | URL 生成 |
| `__PUBLIC__` | `/static` | 公共资源 |
| `__ROOT__` | `/` | 根路径 |

## 使用方式

### 集成在 migrate 命令中（推荐）

```bash
phplift migrate /var/www/old-shop --target 8.0 --write
```

`migrate` 命令会自动扫描 `.html` 和 `.tpl` 文件并转换模板语法。报告中会显示：
```
Templates changed: 45/120
```

### 编程方式调用

```php
use PHPLift\Template\TemplateMigrator;

$migrator = new TemplateMigrator();

// 迁移单个文件
$result = $migrator->migrateFile('/path/to/template.html', dryRun: false);
echo $result->changed;       // true
echo $result->appliedRules;  // ['volist-open', 'volist-close', 'magic-public']

// 迁移字符串
$result = $migrator->migrate('<volist name="list" id="vo">{$vo.name}</volist>');
echo $result->transformed;   // {volist name="list" id="vo"}{$vo.name}{/volist}
```

## 转换示例

**转换前（TP3 模板）：**
```html
<include file="Public/header" />
<volist name="list" id="vo">
  <if condition="$vo.status eq 1">
    <span class="active">{$vo.name}</span>
  <else/>
    <span class="inactive">{$vo.name}</span>
  </if>
</volist>
<script src="__PUBLIC__/js/app.js"></script>
```

**转换后（TP6 模板）：**
```html
{include file="Public/header" /}
{volist name="list" id="vo"}
  {if condition="$vo.status eq 1"}
    <span class="active">{$vo.name}</span>
  {else /}
    <span class="inactive">{$vo.name}</span>
  {/if}
{/volist}
<script src="/static/js/app.js"></script>
```

## 注意事项

- `{$var}` 和 `{:func()}` 在 TP3 和 TP6 中语法相同，无需转换
- 自定义标签库（如 `<cx:block>`）不在自动转换范围内，需手动处理
- 模板中的 PHP 代码块（`<?php ?>`）不受影响
