# 更新日志

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
