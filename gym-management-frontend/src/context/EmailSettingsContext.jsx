import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as emailSettingsService from '../services/emailSettingsService'
import { useToast } from './ToastContext'

const EmailSettingsContext = createContext(null)

export function EmailSettingsProvider({ children }) {
  const [settings, setSettings] = useState(null)
  const [status, setStatus] = useState('loading') // loading | ready | error
  const { showToast } = useToast()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      setSettings(await emailSettingsService.getEmailSettings())
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  const save = useCallback(
    async (input) => {
      const updated = await emailSettingsService.updateEmailSettings(input)
      setSettings(updated)
      showToast('Email settings saved.')
      return updated
    },
    [showToast]
  )

  const sendTest = useCallback(
    async (to) => {
      await emailSettingsService.sendTestEmail(to)
      showToast(`Test email sent to ${to}.`)
    },
    [showToast]
  )

  return (
    <EmailSettingsContext.Provider value={{ settings, status, save, sendTest }}>
      {children}
    </EmailSettingsContext.Provider>
  )
}

export function useEmailSettings() {
  const ctx = useContext(EmailSettingsContext)
  if (!ctx) throw new Error('useEmailSettings must be used within EmailSettingsProvider')
  return ctx
}
