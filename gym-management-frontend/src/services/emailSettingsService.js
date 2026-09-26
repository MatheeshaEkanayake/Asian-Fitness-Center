// Backend: gym-management-backend/app/Http/Controllers/Api/EmailSettingsController.php
//   getEmailSettings()          → GET  /api/setup/email
//   updateEmailSettings(input)  → PUT  /api/setup/email
//   sendTestEmail(to)           → POST /api/setup/email/test

import { apiClient } from './apiClient'

export function getEmailSettings() {
  return apiClient.get('/setup/email')
}

export function updateEmailSettings(input) {
  return apiClient.put('/setup/email', input)
}

export function sendTestEmail(to) {
  return apiClient.post('/setup/email/test', { to })
}
