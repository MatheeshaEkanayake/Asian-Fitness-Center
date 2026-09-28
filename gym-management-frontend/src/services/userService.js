// Backend: gym-management-backend/app/Http/Controllers/Api/UserController.php
// Staff accounts come from registrations: everyone signs up on /signup,
// then an admin grants a registration a role here (createUser). A linked
// user's name, email, username and password stay on the registration.
//
//   listUsers()                  → GET    /api/setup/users
//   listGrantCandidates()        → GET    /api/setup/users/candidates (registered, not yet staff)
//   createUser(input)            → POST   /api/setup/users            ({ memberId, roleId, branchId })
//   updateUser(id, updates)      → PUT    /api/setup/users/{id}
//   resetUserPassword(id, pw)    → POST   /api/setup/users/{id}/reset-password
//   deactivateUser(id)           → DELETE /api/setup/users/{id}  (status → Inactive)
//   enrollOnDevice(id, input)    → POST   /api/setup/users/{id}/enroll (fingerprint/face on the door device)

import { apiClient } from './apiClient'

export async function listUsers() {
  const page = await apiClient.get('/setup/users')
  return page.data
}

export function listGrantCandidates() {
  return apiClient.get('/setup/users/candidates')
}

export function createUser(input) {
  return apiClient.post('/setup/users', input)
}

export function updateUser(userId, updates) {
  return apiClient.put(`/setup/users/${userId}`, updates)
}

export function resetUserPassword(userId, password) {
  return apiClient.post(`/setup/users/${userId}/reset-password`, { password })
}

export function deactivateUser(userId) {
  return apiClient.delete(`/setup/users/${userId}`)
}

// input: { type: 'finger', fingerId: 0–9 } or { type: 'face' }
export function enrollOnDevice(userId, input) {
  return apiClient.post(`/setup/users/${userId}/enroll`, input)
}

// { refreshError, enrollments: [{ kind: 'face'|'finger', fingerId, status: 'registered'|'pending'|'failed', returnCode, lastAttemptAt }] }
export function getEnrollmentStatus(userId) {
  return apiClient.get(`/setup/users/${userId}/enrollments`)
}
