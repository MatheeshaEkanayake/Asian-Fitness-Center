import { FiMoon, FiSun } from 'react-icons/fi'
import { useTheme } from '../../context/ThemeContext'

// Sun/moon button that flips between light and dark mode. Shows the mode
// you'd switch *to*, like the ERP app's navbar toggle.
export default function ThemeToggle({ className = '' }) {
  const { mode, toggleColorMode } = useTheme()
  const isDark = mode === 'dark'
  const label = isDark ? 'Switch to light mode' : 'Switch to dark mode'

  return (
    <button
      type="button"
      onClick={toggleColorMode}
      title={label}
      aria-label={label}
      className={`grid h-9 w-9 shrink-0 place-items-center rounded-full border border-[color:var(--color-line)] bg-[color:var(--color-paper)] text-[color:var(--color-ink-soft)] transition-colors hover:border-[color:var(--color-line-strong)] hover:text-[color:var(--color-ink)] ${className}`}
    >
      {isDark ? <FiSun className="h-4 w-4" /> : <FiMoon className="h-4 w-4" />}
    </button>
  )
}
