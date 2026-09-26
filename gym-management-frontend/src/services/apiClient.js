// Thin fetch wrapper for the Laravel backend. Every service file
// (userService.js, roleService.js, memberService.js, paymentService.js,
// attendanceService.js, etc.) goes through this instead of hand-rolling
// fetch() + auth headers + JSON parsing each time.
//
// NOTE: memberService.js/paymentService.js previously stayed mock-only
// (operating on src/services/db.js); they now use this client too, so the
// whole app is backed by the real MySQL-backed Laravel API.

import { keysToCamel, keysToSnake } from '../utils/apiCase'

const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'
const TOKEN_KEY = 'gym_auth_token'

export function getToken() {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function setToken(token) {
  try {
    localStorage.setItem(TOKEN_KEY, token)
  } catch {
    // ignore (private browsing, storage disabled, etc.)
  }
}

export function clearToken() {
  try {
    localStorage.removeItem(TOKEN_KEY)
  } catch {
    // ignore
  }
}

// Set by AuthContext so a 401 from any request can clear auth state and
// bounce to /login, without this module importing the context directly.
let unauthorizedHandler = null
export function onUnauthorized(handler) {
  unauthorizedHandler = handler
}

class ApiError extends Error {
  constructor(message, { status, errors } = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

async function request(path, { method = 'GET', body, formData, headers = {} } = {}) {
  const token = getToken()

  const init = {
    method,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
  }

  if (formData) {
    // Laravel doesn't parse multipart bodies on PUT/PATCH, so form-data
    // updates are sent as POST with a spoofed _method field.
    if (method !== 'POST') formData.append('_method', method)
    init.method = 'POST'
    init.body = formData
  } else if (body !== undefined) {
    init.headers['Content-Type'] = 'application/json'
    init.body = JSON.stringify(keysToSnake(body))
  }

  const response = await fetch(`${BASE_URL}${path}`, init)

  if (response.status === 401) {
    clearToken()
    unauthorizedHandler?.()
    throw new ApiError('Unauthenticated.', { status: 401 })
  }

  const text = await response.text()
  const data = text ? keysToCamel(JSON.parse(text)) : null

  if (!response.ok) {
    throw new ApiError(data?.message || 'Something went wrong.', {
      status: response.status,
      errors: data?.errors,
    })
  }

  return data
}

async function downloadBlob(path) {
  const token = getToken()
  const response = await fetch(`${BASE_URL}${path}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })

  if (response.status === 401) {
    clearToken()
    unauthorizedHandler?.()
    throw new ApiError('Unauthenticated.', { status: 401 })
  }
  if (!response.ok) {
    throw new ApiError('Download failed.', { status: response.status })
  }

  return response.blob()
}

export const apiClient = {
  get: (path) => request(path),
  post: (path, body) => request(path, { method: 'POST', body }),
  put: (path, body) => request(path, { method: 'PUT', body }),
  patch: (path, body) => request(path, { method: 'PATCH', body }),
  delete: (path) => request(path, { method: 'DELETE' }),
  upload: (path, formData, method = 'POST') => request(path, { method, formData }),
  downloadBlob,
}
