// Backend: gym-management-backend/app/Http/Controllers/Api/GuestController.php
//   listGuests()            → GET    /api/guests
//   getGuest(id)            → GET    /api/guests/{id}
//   promoteGuest(id, input) → POST   /api/guests/{id}/promote   (→ Active member)
//   deleteGuest(id)         → DELETE /api/guests/{id}

import { apiClient } from './apiClient'

export function listGuests() {
  return apiClient.get('/guests')
}

export function getGuest(guestId) {
  return apiClient.get(`/guests/${guestId}`)
}

export function promoteGuest(guestId, input) {
  return apiClient.post(`/guests/${guestId}/promote`, input)
}

export function deleteGuest(guestId) {
  return apiClient.delete(`/guests/${guestId}`)
}
