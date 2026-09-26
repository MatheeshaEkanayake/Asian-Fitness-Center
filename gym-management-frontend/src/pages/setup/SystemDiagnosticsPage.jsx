import { useEffect, useState } from 'react'
import * as diagnosticsService from '../../services/diagnosticsService'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import StatusBadge from '../../components/shared/StatusBadge'

function formatBytes(bytes) {
  if (!bytes) return '—'
  const gb = bytes / 1024 ** 3
  return `${gb.toFixed(1)} GB`
}

// Read-only, stateless — no Context, just local useState/useEffect.
export default function SystemDiagnosticsPage() {
  const [data, setData] = useState(null)
  const [status, setStatus] = useState('loading')

  useEffect(() => {
    diagnosticsService
      .getDiagnostics()
      .then((d) => {
        setData(d)
        setStatus('ready')
      })
      .catch(() => setStatus('error'))
  }, [])

  if (status === 'loading') {
    return <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Running diagnostics…</div>
  }

  if (status === 'error' || !data) {
    return <div className="py-16 text-center text-sm text-[color:var(--color-danger)]">Could not load diagnostics.</div>
  }

  const rows = [
    { label: 'PHP version', value: data.phpVersion },
    { label: 'Laravel version', value: data.laravelVersion },
    {
      label: 'Database',
      value: `${data.database.driver}`,
      badge: data.database.connected ? 'Active' : 'Failed',
    },
    { label: 'Storage writable', value: data.storageWritable ? 'Yes' : 'No', badge: data.storageWritable ? 'Active' : 'Failed' },
    { label: 'Cache driver', value: data.cacheDriver },
    { label: 'Queue driver', value: data.queueDriver },
    { label: 'Disk free space', value: `${formatBytes(data.diskFreeBytes)} of ${formatBytes(data.diskTotalBytes)}` },
  ]

  return (
    <div className="max-w-lg">
      <PageHeader
        back={{ to: '/setup', label: 'Back to setup' }}
        title="System Diagnostics"
        description="Live snapshot of server health."
      />
      <Card>
        <dl className="divide-y divide-[color:var(--color-line)]">
          {rows.map((row) => (
            <div key={row.label} className="flex items-center justify-between px-5 py-3">
              <dt className="text-sm text-[color:var(--color-ink-soft)]">{row.label}</dt>
              <dd className="flex items-center gap-2 text-sm font-medium text-[color:var(--color-ink)]">
                {row.value}
                {row.badge && <StatusBadge status={row.badge} />}
              </dd>
            </div>
          ))}
        </dl>
      </Card>
    </div>
  )
}
