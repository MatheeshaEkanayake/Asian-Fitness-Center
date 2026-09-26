import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as userService from '../services/userService'
import { useToast } from './ToastContext'

const UsersContext = createContext(null)

export function UsersProvider({ children }) {
  const [users, setUsers] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error
  const { showToast } = useToast()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      setUsers(await userService.listUsers())
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  const addUser = useCallback(
    async (input) => {
      const user = await userService.createUser(input)
      setUsers((current) => [user, ...current])
      showToast(`Staff access granted to ${user.fullName}.`)
      return user
    },
    [showToast]
  )

  const editUser = useCallback(
    async (userId, updates) => {
      const updated = await userService.updateUser(userId, updates)
      setUsers((current) => current.map((u) => (u.id === userId ? updated : u)))
      showToast(`${updated.fullName} updated.`)
      return updated
    },
    [showToast]
  )

  const resetPassword = useCallback(
    async (userId, password) => {
      await userService.resetUserPassword(userId, password)
      showToast('Password reset.')
    },
    [showToast]
  )

  const deactivateUser = useCallback(
    async (userId) => {
      const updated = await userService.deactivateUser(userId)
      setUsers((current) => current.map((u) => (u.id === userId ? updated : u)))
      showToast(`${updated.fullName} deactivated.`)
      return updated
    },
    [showToast]
  )

  const getUserById = useCallback((userId) => users.find((u) => u.id === userId), [users])

  return (
    <UsersContext.Provider
      value={{ users, status, reload, addUser, editUser, resetPassword, deactivateUser, getUserById }}
    >
      {children}
    </UsersContext.Provider>
  )
}

export function useUsers() {
  const ctx = useContext(UsersContext)
  if (!ctx) throw new Error('useUsers must be used within UsersProvider')
  return ctx
}
