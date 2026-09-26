// Backend: gym-management-backend/app/Http/Controllers/Api/RoleController.php
//   listRoles()               → GET    /api/setup/roles
//   createRole(input)         → POST   /api/setup/roles
//   updateRole(id, updates)   → PUT    /api/setup/roles/{id}
//   deleteRole(id)            → DELETE /api/setup/roles/{id}

import { apiClient } from './apiClient'

export function listRoles() {
  return apiClient.get('/setup/roles')
}

export function createRole(input) {
  return apiClient.post('/setup/roles', input)
}

export function updateRole(roleId, updates) {
  return apiClient.put(`/setup/roles/${roleId}`, updates)
}

export function deleteRole(roleId) {
  return apiClient.delete(`/setup/roles/${roleId}`)
}
