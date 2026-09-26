// Auth/permission state for the whole app. Permissions are always
// re-derived server-side via authService.me() — localStorage only ever
// holds the bearer token (see src/services/apiClient.js), never a cached
// permission list, so a role change on the backend takes effect on the
// next page load without any client-side cache to invalidate.
//
// Everyone registers via signup and signs in with one username/password.
// `user.accountType` is 'staff' when an admin has granted that registration
// a role (Setup > Users), otherwise 'member'. Members get no permissions and
// are kept to their own area by RequireAuth's `account` prop.

import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as authService from '../services/authService'
import { getToken, onUnauthorized } from '../services/apiClient'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  // loading | ready | anonymous
  const [status, setStatus] = useState('loading')

  const loadFromToken = useCallback(async () => {
    if (!getToken()) {
      setStatus('anonymous')
      return
    }
    try {
      const me = await authService.me()
      setUser(me)
      setStatus('ready')
    } catch {
      setUser(null)
      setStatus('anonymous')
    }
  }, [])

  useEffect(() => {
    loadFromToken()
  }, [loadFromToken])

  useEffect(() => {
    onUnauthorized(() => {
      setUser(null)
      setStatus('anonymous')
    })
  }, [])

  const login = useCallback(async (username, password) => {
    const loggedInUser = await authService.login(username, password)
    setUser(loggedInUser)
    setStatus('ready')
    return loggedInUser
  }, [])

  const signup = useCallback(async (input) => {
    const member = await authService.signup(input)
    setUser(member)
    setStatus('ready')
    return member
  }, [])

  const logout = useCallback(async () => {
    await authService.logout()
    setUser(null)
    setStatus('anonymous')
  }, [])

  const hasPermission = useCallback(
    (permission) => {
      if (!user) return false
      if (user.isAdmin) return true
      return (user.permissions || []).includes(permission)
    },
    [user]
  )

  return (
    <AuthContext.Provider
      value={{ user, status, login, signup, logout, hasPermission, reload: loadFromToken }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
