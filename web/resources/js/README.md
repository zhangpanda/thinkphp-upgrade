# ThinkPHP-Upgrade Web UI 前端结构

技术栈：Vue 3 + Vite + Arco Design + Monaco Editor

## 页面结构

```
web/resources/js/
├── app.js                    # Vue 入口
├── router.js                 # 路由定义
├── pages/
│   ├── Import.vue            # Step 1: 项目导入（选择目录 + 扫描结果）
│   ├── Configure.vue         # Step 2: 配置目标版本
│   ├── Analysis.vue          # Step 3: 分析预览（统计图 + 问题列表）
│   ├── Confirm.vue           # Step 4: 交互确认（Monaco Diff Editor）
│   └── Report.vue            # Step 5: 完成报告
├── components/
│   ├── DiffViewer.vue        # Monaco Diff 组件
│   ├── ProgressBar.vue       # 实时进度条
│   ├── RuleList.vue          # 规则列表/过滤
│   └── StepNav.vue           # 顶部步骤导航
└── api/
    └── tp-upgrade.js            # API 调用封装
```

## 路由定义

| 路径 | 页面 | 说明 |
|------|------|------|
| `/` | Import.vue | 选择项目目录 |
| `/configure` | Configure.vue | 设置目标框架版本 |
| `/analysis` | Analysis.vue | 查看分析结果 |
| `/confirm` | Confirm.vue | 逐条确认变更 |
| `/report` | Report.vue | 最终报告 |

## WebSocket 事件

| 事件 | 方向 | 数据 |
|------|------|------|
| `analysis.progress` | Server → Client | `{current, total, file}` |
| `analysis.complete` | Server → Client | `{summary}` |
| `transform.progress` | Server → Client | `{current, total, file}` |
