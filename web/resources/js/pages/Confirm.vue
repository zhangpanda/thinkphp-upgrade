<template>
  <div class="confirm-page">
    <a-card :title="`确认变更 (${current}/${total})`">
      <template #extra>
        <a-tag color="blue">{{ change.ruleName }}</a-tag>
      </template>

      <p class="file-path">📄 {{ change.filePath }}</p>

      <!-- Monaco Diff Editor -->
      <div ref="diffEditor" class="diff-editor"></div>

      <div class="actions" style="margin-top: 16px; display: flex; gap: 8px;">
        <a-button type="primary" @click="accept">✓ 接受</a-button>
        <a-button @click="skip">✗ 跳过</a-button>
        <a-button @click="aiSuggest" :loading="aiLoading">🤖 AI 建议</a-button>
      </div>

      <a-progress :percent="Math.round(current / total * 100)" style="margin-top: 16px" />
    </a-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { confirmChange, skipChange } from '../api/phplift'

const current = ref(1)
const total = ref(0)
const change = ref({ filePath: '', ruleName: '', oldCode: '', newCode: '' })
const aiLoading = ref(false)
const diffEditor = ref(null)

function accept() {
  confirmChange(change.value.id)
  current.value++
  loadNext()
}

function skip() {
  skipChange(change.value.id)
  current.value++
  loadNext()
}

async function aiSuggest() {
  aiLoading.value = true
  // Call AI endpoint
  aiLoading.value = false
}

function loadNext() {
  // Load next change from API
}

onMounted(() => {
  // Initialize Monaco Diff Editor
})
</script>

<style scoped>
.diff-editor { height: 400px; border: 1px solid #e8e8e8; border-radius: 4px; }
.file-path { color: #666; font-family: monospace; }
</style>
