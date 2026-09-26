import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as gymSettingsService from '../services/gymSettingsService'
import { useToast } from './ToastContext'
import { useBranding } from './BrandingContext'

const GymSettingsContext = createContext(null)

export function GymSettingsProvider({ children }) {
  const [settings, setSettings] = useState(null)
  const [status, setStatus] = useState('loading') // loading | ready | error
  const { showToast } = useToast()
  const { applyBranding } = useBranding()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      setSettings(await gymSettingsService.getGymSettings())
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
      const updated = await gymSettingsService.updateGymSettings(input)
      setSettings(updated)
      applyBranding(updated) // sidebar, tab title, etc. update right away
      showToast('Gym settings saved.')
      return updated
    },
    [showToast, applyBranding]
  )

  return <GymSettingsContext.Provider value={{ settings, status, save }}>{children}</GymSettingsContext.Provider>
}

export function useGymSettings() {
  const ctx = useContext(GymSettingsContext)
  if (!ctx) throw new Error('useGymSettings must be used within GymSettingsProvider')
  return ctx
}
