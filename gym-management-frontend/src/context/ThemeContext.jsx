import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

// Light/dark mode. The choice is saved in localStorage and applied as
// `data-theme` on <html>; index.css swaps the color tokens off that attribute.
// index.html applies the saved value before React loads so a dark-mode
// reload doesn't flash light first — keep the storage key in sync there.
const STORAGE_KEY = 'themeMode'

const ThemeContext = createContext(null)

function readSavedMode() {
  try {
    return localStorage.getItem(STORAGE_KEY) === 'dark' ? 'dark' : 'light'
  } catch {
    return 'light'
  }
}

export function ThemeProvider({ children }) {
  const [mode, setMode] = useState(readSavedMode)

  useEffect(() => {
    document.documentElement.dataset.theme = mode
    try {
      localStorage.setItem(STORAGE_KEY, mode)
    } catch {
      // Storage blocked (private mode etc.) — the choice just won't persist.
    }
  }, [mode])

  const toggleColorMode = useCallback(() => {
    setMode((prev) => (prev === 'light' ? 'dark' : 'light'))
  }, [])

  const value = useMemo(() => ({ mode, toggleColorMode }), [mode, toggleColorMode])

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}

export function useTheme() {
  const ctx = useContext(ThemeContext)
  if (!ctx) throw new Error('useTheme must be used within ThemeProvider')
  return ctx
}
