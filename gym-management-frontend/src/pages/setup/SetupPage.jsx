import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { SETUP_TILES } from '../../config/navigationTree'
import PageHeader from '../../components/shared/PageHeader'

export default function SetupPage() {
  const navigate = useNavigate()
  const { hasPermission } = useAuth()

  const tiles = SETUP_TILES.filter((tile) => hasPermission(tile.permission))

  return (
    <div>
      <PageHeader title="Setup" description="Configure accounts, permissions, and system settings." />

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {tiles.map((tile) => (
          <button
            key={tile.path}
            type="button"
            onClick={() => navigate(tile.path)}
            className="text-left p-5 rounded-lg border border-[color:var(--color-line)] bg-[color:var(--color-surface)] transition-all hover:border-[color:var(--color-brand)] hover:shadow-sm"
          >
            <h2 className="font-display text-base font-semibold text-[color:var(--color-ink)]">
              {tile.label}
            </h2>
            <p className="mt-1 text-sm text-[color:var(--color-ink-soft)]">{tile.description}</p>
          </button>
        ))}
      </div>
    </div>
  )
}
