<template>
  <div class="analysis-page">
    <a-card title="📊 分析结果">
      <div class="stats" v-if="summary">
        <a-statistic title="总问题数" :value="summary.total" />
        <a-statistic title="可自动修复" :value="summary.autoFixable" :value-style="{ color: '#52c41a' }" />
        <a-statistic title="需手动/AI" :value="summary.manual" :value-style="{ color: '#ff4d4f' }" />
      </div>

      <a-table :data="ruleStats" :columns="columns" style="margin-top: 16px" />

      <div style="margin-top: 16px; display: flex; gap: 8px;">
        <a-button @click="exportReport('html')">📄 导出 HTML 报告</a-button>
        <a-button type="primary" @click="$router.push('/confirm')">开始迁移 →</a-button>
      </div>
    </a-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getAnalysisStatus, getReport } from '../api/phplift'

const summary = ref(null)
const ruleStats = ref([])
const columns = [
  { title: '规则', dataIndex: 'rule' },
  { title: '数量', dataIndex: 'count' },
  { title: '类型', dataIndex: 'type' },
]

async function exportReport(format) {
  const html = await getReport('current', format)
  const blob = new Blob([html], { type: 'text/html' })
  const url = URL.createObjectURL(blob)
  window.open(url)
}

onMounted(async () => {
  const data = await getAnalysisStatus('current')
  summary.value = data.summary
  ruleStats.value = data.byRule
})
</script>

<style scoped>
.stats { display: flex; gap: 2em; }
</style>
