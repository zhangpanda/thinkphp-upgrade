# 更新日志

## v0.3.0 (2026-06-15)

### 新功能

- **Web UI** — `tp-upgrade serve /path` 启动交互式确认界面
  - 内置 PHP Web Server，无需 Laravel 或其他框架
  - Diff 视图逐条展示变更，支持接受/跳过操作
  - JSON API（/api/project、/api/changes、confirm/skip/confirm-all）
  - 全部接受后自动写入文件（带 .bak 备份）

- **模板文件迁移** — `TemplateMigrator`
  - 转换 ThinkPHP 3.x 模板标签：volist / foreach / if / eq / neq / empty / notempty / include
  - 转换魔术常量：`__URL__` → `{:url('/')}`、`__PUBLIC__` → `/static`、`__ROOT__` → `/`
  - 集成到 `migrate` 命令，自动处理 .html / .tpl 文件
  - 正则驱动，与 PHP AST 转换独立

### 改进

- `migrate` 命令报告中新增模板文件统计
- ConfigCallRule KEY_MAP 从 8 条扩展到 20 条（覆盖 session/cache/app/route）
- InputCallRule 支持逗号分隔多过滤器（`'htmlspecialchars,strip_tags'`）
- UrlGenerateRule 正确处理带 query string 的 URL（`U('User/add?id=1')`）
- ModelCallRule 自动 ucfirst 确保 PSR-4 类名合规
- ControllerMigrationRule 幂等性（跳过已迁移的类）
- MigrationPlan 版本规范化别名（3.0→3.2, 6.1→6.0, 8.1→8.0）

### 安全加固

- ServeCommand：`escapeshellarg()` 全参数转义 + 端口/版本格式校验
- Web UI：CSRF token 防护 + 路径穿越防御 + XSS 修复
- ApiHandler：`php://input` 单次读取、HTTP 状态码规范、写入失败检查
- OpenAIProvider：SSL 强制验证 + 1MB 响应大小限制
- CodeTransformer/TemplateMigrator：备份写入失败抛异常
- MigrateCommand：`tempnam()` 失败检查、报告写入失败提示
- SECURITY.md：漏洞报告流程 + API 稳定性声明

## v0.1.0 (2026-06-10)

### 首个版本

**核心功能：**
- 项目扫描器：自动检测 ThinkPHP 版本（composer.json + 特征文件）
- AST 代码分析引擎
- AST 代码转换引擎（dry-run + 自动备份）
- CLI 工具：`phplift analyze` + `phplift transform`

**内置规则（7 条）：**
- `tp3-add-namespace` — 添加命名空间
- `tp3-controller-migration` — 控制器继承 + display/assign 迁移
- `tp3-model-call` — M()/D() → 模型静态调用
- `tp3-config-call` — C() → config() + 配置键映射
- `tp3-input-call` — I() → $request 方法
- `tp3-url-generate` — U() → url()
- `tp3-is-post` — IS_POST/IS_GET → $request 方法

**AI 辅助：**
- 支持 OpenAI / DeepSeek API
- 无 API Key 时自动降级为纯规则模式

**报告：**
- Console 格式报告
- JSON 格式报告（CI/CD 集成）

## v0.2.0 (2026-06-12)

### 新功能

- **`migrate` 命令** — 一键完成转换+语法检查+报告生成
  - 自动检测需要手动处理的代码模式
  - 生成 JSON 格式迁移报告
  - 语法检查不通过时返回失败退出码（CI/CD 友好）

### 改进

- D() 和 M() 区分语义：D() → `new Model()`，M() → `Model::query()`
- I() 第三个参数（过滤函数）保留为函数包裹
- C('key', value) 设置操作正确转为 `config(['key' => value])`
- declare(strict_types) 正确保留在 namespace 之前
- Anthropic Provider 多条 system 消息自动拼接
- Template 支持嵌套 `{{#if}}` 条件

### Bug 修复

- 修复 36 个 Bug（详见 Git 历史）
- 所有 Provider 对异常 API 响应的防御处理
- MCP Server 工具/资源执行异常不再崩溃
- RecursiveCharacterSplitter 不再因 overlap >= chunkSize 死循环
- Agent 自动过滤 Memory 中的孤立 tool 消息
