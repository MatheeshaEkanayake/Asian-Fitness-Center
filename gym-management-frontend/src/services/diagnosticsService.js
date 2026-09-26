// Backend: gym-management-backend/app/Http/Controllers/Api/SystemDiagnosticsController.php
//   getDiagnostics() → GET /api/setup/diagnostics

import { apiClient } from './apiClient'

export function getDiagnostics() {
  return apiClient.get('/setup/diagnostics')
}
