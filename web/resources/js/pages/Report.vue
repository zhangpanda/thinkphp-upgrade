<template>
  <div class="report-page">
    <a-card title="✅ 迁移完成">
      <a-result status="success" title="迁移已完成" :subtitle="subtitle">
        <template #extra>
          <a-button type="primary" @click="downloadHtml">📄 下载 HTML 报告</a-button>
          <a-button @click="downloadJson">📊 下载 JSON 报告</a-button>
        </template>
      </a-result>

      <a-descriptions title="迁移统计" bordered style="margin-top: 24px">
        <a-descriptions-item label="处理文件数">{{ report.filesChanged }}</a-descriptions-item>
        <a-descriptions-item label="自动修复">{{ report.autoFixed }}</a-descriptions-item>
        <a-descriptions-item label="手动跳过">{{ report.skipped }}</a-descriptions-item>
        <a-descriptions-item label="AI 辅助">{{ report.aiAssisted }}</a-descriptions-item>
        <a-descriptions-item label="耗时">{{ report.duration }}</a-descriptions-item>
      </a-descriptions>

      <a-alert type="info" style="margin-top: 16px">
        <template #title>后续步骤</template>
        <ol>
          <li>检查转换后的代码，运行项目测试</li>
          <li>手动处理标记为"跳过"的文件</li>
          <li>更新 composer.json 依赖到目标框架版本</li>
          <li>调整路由配置和中间件注册</li>
        </ol>
      </a-alert>
    </a-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getReport } from '../api/phplift'

const report = ref({ filesChanged: 0, autoFixed: 0, skipped: 0, aiAssisted: 0, duration: '0s' })

const subtitle = computed(() =>
  `共处理 ${report.value.filesChanged} 个文件，自动修复 ${report.value.autoFixed} 处`
)

async function downloadHtml() {
  const html = await getReport('current', 'html')
  download(html, 'phplift-report.html', 'text/html')
}

async function downloadJson() {
  const json = await getReport('current', 'json')
  download(JSON.stringify(json, null, 2), 'phplift-report.json', 'application/json')
}

function download(content, filename, type) {
  const blob = new Blob([content], { type })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(async () => {
  const data = await getReport('current', 'json')
  report.value = data.summary || report.value
})
</script>
