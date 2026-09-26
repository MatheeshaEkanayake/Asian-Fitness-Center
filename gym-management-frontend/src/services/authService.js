// Backend: gym-management-backend/app/Http/Controllers/Api/AuthController.php
//   login()       → POST /api/auth/login          (everyone, by username; the
//                                                  bootstrap admin types its email)
//   signup()      → POST /api/auth/signup         (public registration)
//   logout() → POST /api/auth/logout
//   me()     → GET  /api/auth/me

import { apiClient, setToken, clearToken } from './apiClient'

// Returns the signed-in account; its accountType ('staff' or 'member') is
// decided by the backend — staff are registrations an admin granted a role.
export async function login(username, password) {
  const data = await apiClient.post('/auth/login', { username, password })
  setToken(data.token)
  return data.user
}

export async function signup(input) {
  const data = await apiClient.post('/auth/signup', input)
  setToken(data.token)
  return data.user
}

export async function logout() {
  try {
    await apiClient.post('/auth/logout')
  } finally {
    clearToken()
  }
}

export function me() {
  return apiClient.get('/auth/me')
}
