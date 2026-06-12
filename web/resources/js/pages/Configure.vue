<template>
  <div class="configure-page">
    <a-card title="⚙️ 配置迁移目标">
      <a-form :model="form" layout="vertical">
        <a-form-item label="当前版本">
          <a-tag color="orange" size="large">{{ currentVersion }}</a-tag>
        </a-form-item>

        <a-form-item label="目标版本">
          <a-radio-group v-model="form.target" direction="vertical">
            <a-radio value="5.1" :disabled="!canTarget('5.1')">ThinkPHP 5.1</a-radio>
            <a-radio value="6.0" :disabled="!canTarget('6.0')">ThinkPHP 6.0（推荐）</a-radio>
            <a-radio value="8.0" :disabled="!canTarget('8.0')">ThinkPHP 8.0</a-radio>
          </a-radio-group>
        </a-form-item>

        <a-form-item label="迁移路径">
          <a-steps :current="migrationSteps.length" size="small">
            <a-step v-for="step in migrationSteps" :key="step" :title="step" />
          </a-steps>
        </a-form-item>

        <a-form-item label="AI 辅助">
          <a-switch v-model="form.useAI" />
          <span style="margin-left: 8px; color: #666">启用 AI 处理复杂迁移场景（需配置 API Key）</span>
        </a-form-item>

        <a-form-item>
          <a-button type="primary" @click="startAnalysis">开始分析 →</a-button>
        </a-form-item>
      </a-form>
    </a-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { startAnalysis as apiStartAnalysis } from '../api/phplift'

const router = useRouter()
const currentVersion = ref('3.2') // From previous scan
const form = reactive({ target: '6.0', useAI: false })

const migrationSteps = computed(() => {
  const path = { '5.1': ['3.2 → 5.1'], '6.0': ['3.2 → 5.1', '5.1 → 6.0'], '8.0': ['3.2 → 5.1', '5.1 → 6.0', '6.0 → 8.0'] }
  return path[form.target] || []
})

function canTarget(version) {
  const versions = ['3.2', '5.1', '6.0', '8.0']
  return versions.indexOf(version) > versions.indexOf(currentVersion.value)
}

async function startAnalysis() {
  await apiStartAnalysis('current')
  router.push('/analysis')
}
</script>
