// Backend: gym-management-backend/app/Http/Controllers/Api/BackupController.php
//   listBackups()        → GET    /api/setup/backups
//   runBackup()          → POST   /api/setup/backups
//   downloadBackup(file) → GET    /api/setup/backups/{file}
//   deleteBackup(file)   → DELETE /api/setup/backups/{file}

import { apiClient } from './apiClient'

export function listBackups() {
  return apiClient.get('/setup/backups')
}

export function runBackup() {
  return apiClient.post('/setup/backups')
}

export function deleteBackup(file) {
  return apiClient.delete(`/setup/backups/${encodeURIComponent(file)}`)
}

export async function downloadBackup(file) {
  const blob = await apiClient.downloadBlob(`/setup/backups/${encodeURIComponent(file)}`)
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = file
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
