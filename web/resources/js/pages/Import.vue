<template>
  <div class="import-page">
    <a-card title="📁 导入项目">
      <a-form :model="form" @submit="handleScan">
        <a-form-item label="项目路径">
          <a-input v-model="form.path" placeholder="/var/www/your-project" />
        </a-form-item>
        <a-form-item>
          <a-button type="primary" html-type="submit" :loading="scanning">
            开始扫描
          </a-button>
        </a-form-item>
      </a-form>

      <div v-if="profile" class="scan-result">
        <a-descriptions title="扫描结果" bordered>
          <a-descriptions-item label="框架">
            {{ profile.framework.name }} {{ profile.framework.version }}
          </a-descriptions-item>
          <a-descriptions-item label="置信度">
            {{ profile.framework.confidence }}%
          </a-descriptions-item>
          <a-descriptions-item label="PHP 文件数">
            {{ profile.phpFiles }}
          </a-descriptions-item>
          <a-descriptions-item label="代码行数">
            {{ profile.totalLines.toLocaleString() }}
          </a-descriptions-item>
        </a-descriptions>

        <a-button type="primary" @click="$router.push('/configure')" style="margin-top: 16px">
          下一步 →
        </a-button>
      </div>
    </a-card>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { scanProject } from '../api/phplift'

const form = reactive({ path: '' })
const scanning = ref(false)
const profile = ref(null)

async function handleScan() {
  scanning.value = true
  try {
    profile.value = await scanProject(form.path)
  } finally {
    scanning.value = false
  }
}
</script>
