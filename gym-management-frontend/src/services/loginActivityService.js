// Backend: gym-management-backend/app/Http/Controllers/Api/LoginActivityController.php
//   listLoginActivity(page, perPage) → GET /api/setup/login-activity?page=N&per_page=M

import { apiClient } from './apiClient'

export function listLoginActivity(page = 1, perPage = 50) {
  return apiClient.get(`/setup/login-activity?page=${page}&per_page=${perPage}`)
}
