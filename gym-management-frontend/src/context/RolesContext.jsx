import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as roleService from '../services/roleService'
import { useToast } from './ToastContext'

const RolesContext = createContext(null)

export function RolesProvider({ children }) {
  const [roles, setRoles] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error
  const { showToast } = useToast()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      setRoles(await roleService.listRoles())
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  const addRole = useCallback(
    async (input) => {
      const role = await roleService.createRole(input)
      setRoles((current) => [...current, role])
      showToast(`${role.name} added.`)
      return role
    },
    [showToast]
  )

  const editRole = useCallback(
    async (roleId, updates) => {
      const updated = await roleService.updateRole(roleId, updates)
      setRoles((current) => current.map((r) => (r.id === roleId ? updated : r)))
      showToast(`${updated.name} updated.`)
      return updated
    },
    [showToast]
  )

  const removeRole = useCallback(
    async (roleId) => {
      const role = roles.find((r) => r.id === roleId)
      await roleService.deleteRole(roleId)
      setRoles((current) => current.filter((r) => r.id !== roleId))
      showToast(`${role?.name || 'Role'} deleted.`)
    },
    [roles, showToast]
  )

  const getRoleById = useCallback((roleId) => roles.find((r) => r.id === roleId), [roles])

  return (
    <RolesContext.Provider value={{ roles, status, reload, addRole, editRole, removeRole, getRoleById }}>
      {children}
    </RolesContext.Provider>
  )
}

export function useRoles() {
  const ctx = useContext(RolesContext)
  if (!ctx) throw new Error('useRoles must be used within RolesProvider')
  return ctx
}
