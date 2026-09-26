// Backend: gym-management-backend/app/Http/Controllers/Api/BranchController.php
//   listBranches()            → GET    /api/setup/branches
//   createBranch(input)       → POST   /api/setup/branches
//   updateBranch(id, updates) → PUT    /api/setup/branches/{id}
//   deleteBranch(id)          → DELETE /api/setup/branches/{id}

import { apiClient } from './apiClient'

export function listBranches() {
  return apiClient.get('/setup/branches')
}

export function createBranch(input) {
  return apiClient.post('/setup/branches', input)
}

export function updateBranch(branchId, updates) {
  return apiClient.put(`/setup/branches/${branchId}`, updates)
}

export function deleteBranch(branchId) {
  return apiClient.delete(`/setup/branches/${branchId}`)
}
