import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as backupService from '../services/backupService'
import { useToast } from './ToastContext'

const BackupsContext = createContext(null)

export function BackupsProvider({ children }) {
  const [backups, setBackups] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error
  const { showToast } = useToast()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      setBackups(await backupService.listBackups())
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  const createBackup = useCallback(async () => {
    await backupService.runBackup()
    showToast('Backup created.')
    await reload()
  }, [reload, showToast])

  const download = useCallback((file) => backupService.downloadBackup(file), [])

  const removeBackup = useCallback(
    async (file) => {
      await backupService.deleteBackup(file)
      setBackups((current) => current.filter((b) => b.file !== file))
      showToast('Backup deleted.')
    },
    [showToast]
  )

  return (
    <BackupsContext.Provider value={{ backups, status, reload, createBackup, download, removeBackup }}>
      {children}
    </BackupsContext.Provider>
  )
}

export function useBackups() {
  const ctx = useContext(BackupsContext)
  if (!ctx) throw new Error('useBackups must be used within BackupsProvider')
  return ctx
}
