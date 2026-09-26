import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as branchService from '../services/branchService'
import { useToast } from './ToastContext'

const BranchesContext = createContext(null)

export function BranchesProvider({ children }) {
  const [branches, setBranches] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error
  const { showToast } = useToast()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      setBranches(await branchService.listBranches())
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  const addBranch = useCallback(
    async (input) => {
      const branch = await branchService.createBranch(input)
      setBranches((current) => [...current, branch])
      showToast(`${branch.name} added.`)
      return branch
    },
    [showToast]
  )

  const editBranch = useCallback(
    async (branchId, updates) => {
      const updated = await branchService.updateBranch(branchId, updates)
      setBranches((current) => current.map((b) => (b.id === branchId ? updated : b)))
      showToast(`${updated.name} updated.`)
      return updated
    },
    [showToast]
  )

  const removeBranch = useCallback(
    async (branchId) => {
      const branch = branches.find((b) => b.id === branchId)
      await branchService.deleteBranch(branchId)
      setBranches((current) => current.filter((b) => b.id !== branchId))
      showToast(`${branch?.name || 'Branch'} deleted.`)
    },
    [branches, showToast]
  )

  const getBranchById = useCallback((branchId) => branches.find((b) => b.id === branchId), [branches])

  return (
    <BranchesContext.Provider
      value={{ branches, status, reload, addBranch, editBranch, removeBranch, getBranchById }}
    >
      {children}
    </BranchesContext.Provider>
  )
}

export function useBranches() {
  const ctx = useContext(BranchesContext)
  if (!ctx) throw new Error('useBranches must be used within BranchesProvider')
  return ctx
}
