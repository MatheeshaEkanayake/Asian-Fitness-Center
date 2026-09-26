import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import * as gymSettingsService from '../services/gymSettingsService'

// The gym's public profile (name, tagline, logo, contact details) from
// Setup > Gym Settings, for everything that shows the gym's identity:
// sidebar, sign-in pages, receipts, browser tab. Available app-wide —
// including before sign-in — unlike GymSettingsContext, which is the
// Setup-only editor.
//
// FALLBACK is only used for fields that haven't been saved in Gym Settings
// yet (a fresh install). The last loaded branding is cached so a reload
// shows the right name/logo straight away instead of flashing the fallback.

const FALLBACK = {
  name: 'Asian Fitness Center',
  tagline: 'Gym operations',
  logoUrl: '/AsianFitnessLogo.svg',
  email: null,
  phone: null,
  address: null,
  website: null,
}

const CACHE_KEY = 'gym_branding'

const BrandingContext = createContext(null)

function readCache() {
  try {
    return JSON.parse(localStorage.getItem(CACHE_KEY)) || null
  } catch {
    return null
  }
}

function withFallback(saved) {
  const branding = { ...FALLBACK }
  for (const [key, value] of Object.entries(saved || {})) {
    if (value) branding[key] = value
  }
  return branding
}

export function BrandingProvider({ children }) {
  const [saved, setSaved] = useState(readCache)

  const applyBranding = useCallback((next) => {
    setSaved(next)
    try {
      localStorage.setItem(CACHE_KEY, JSON.stringify(next))
    } catch {
      // Storage blocked — branding still works, it just isn't cached.
    }
  }, [])

  useEffect(() => {
    gymSettingsService
      .getGymBranding()
      .then(applyBranding)
      .catch(() => {
        // Backend unreachable: keep the cached/fallback branding.
      })
  }, [applyBranding])

  const branding = useMemo(() => withFallback(saved), [saved])

  // Browser tab title and favicon follow the saved branding.
  useEffect(() => {
    document.title = `${branding.name} — Gym Management`
    const icon = document.querySelector('link[rel="icon"]')
    if (icon) {
      icon.href = branding.logoUrl
      icon.removeAttribute('type')
    }
  }, [branding.name, branding.logoUrl])

  const value = useMemo(() => ({ branding, applyBranding }), [branding, applyBranding])

  return <BrandingContext.Provider value={value}>{children}</BrandingContext.Provider>
}

export function useBranding() {
  const ctx = useContext(BrandingContext)
  if (!ctx) throw new Error('useBranding must be used within BrandingProvider')
  return ctx
}
