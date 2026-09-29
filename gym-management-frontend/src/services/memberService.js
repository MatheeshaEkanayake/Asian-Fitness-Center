// Backend: gym-management-backend/app/Http/Controllers/Api/MemberController.php
//   listMembers()               → GET    /api/members
//   getMember(memberId)         → GET    /api/members/{id}
//   createMember(input)         → POST   /api/members
//   updateMember(id, updates)   → PUT    /api/members/{id}
//   setMemberStatus(id, status) → PATCH  /api/members/{id}/status
//   setGateAccess(id, enabled)  → PATCH  /api/members/{id}/gate-access  (status Active ↔ Inactive)
//   archiveMember(memberId)     → DELETE /api/members/{id}  (archived; purged after 6 months)
//   enrollOnDevice(id, input)   → POST   /api/members/{id}/enroll  (fingerprint/face on the door device)
//
// NOTE: this file previously operated on the in-memory src/services/db.js mock
// store; it now calls the real Laravel API via apiClient (same pattern as
// userService.js/roleService.js). Function names/signatures are unchanged so
// MembersContext.jsx and every page that consumes it needed no changes.

import { apiClient } from './apiClient'

export async function listMembers() {
  const page = await apiClient.get('/members')
  return page.data
}

export function getMember(memberId) {
  return apiClient.get(`/members/${memberId}`)
}

export function createMember(input) {
  return apiClient.post('/members', input)
}

export function updateMember(memberId, updates) {
  return apiClient.put(`/members/${memberId}`, updates)
}

export function setMemberStatus(memberId, status) {
  return apiClient.patch(`/members/${memberId}/status`, { status })
}

export function setGateAccess(memberId, enabled) {
  return apiClient.patch(`/members/${memberId}/gate-access`, { enabled })
}

// Archived, not erased: payments still show the member. The backend
// removes them for good after 6 months (members:purge-archived).
export function archiveMember(memberId) {
  return apiClient.delete(`/members/${memberId}`)
}

// input: { type: 'finger', fingerId: 0–9 } or { type: 'face' }
export function enrollOnDevice(memberId, input) {
  return apiClient.post(`/members/${memberId}/enroll`, input)
}

// { refreshError, enrollments: [{ kind: 'face'|'finger', fingerId, status: 'registered'|'pending'|'failed', returnCode, lastAttemptAt }] }
export function getEnrollmentStatus(memberId) {
  return apiClient.get(`/members/${memberId}/enrollments`)
}
