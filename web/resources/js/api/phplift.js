const BASE = '/api/phplift'

export async function scanProject(path) {
  const res = await fetch(`${BASE}/projects`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ path }),
  })
  return res.json()
}

export async function startAnalysis(projectId) {
  const res = await fetch(`${BASE}/projects/${projectId}/analyze`, { method: 'POST' })
  return res.json()
}

export async function getAnalysisStatus(projectId) {
  const res = await fetch(`${BASE}/projects/${projectId}/analysis`)
  return res.json()
}

export async function startTransform(projectId, options = {}) {
  const res = await fetch(`${BASE}/projects/${projectId}/transform`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(options),
  })
  return res.json()
}

export async function confirmChange(projectId, changeId) {
  const res = await fetch(`${BASE}/projects/${projectId}/transform/confirm`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ change_id: changeId }),
  })
  return res.json()
}

export async function skipChange(projectId, changeId) {
  const res = await fetch(`${BASE}/projects/${projectId}/transform/skip`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ change_id: changeId }),
  })
  return res.json()
}

export async function getReport(projectId, format = 'json') {
  const endpoint = format === 'html' ? 'report/html' : 'report'
  const res = await fetch(`${BASE}/projects/${projectId}/${endpoint}`)
  return format === 'html' ? res.text() : res.json()
}
