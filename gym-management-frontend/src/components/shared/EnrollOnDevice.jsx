import { useCallback, useEffect, useState } from 'react'
import Button from './Button'
import { Select } from './FormField'

// Fingerprint / face enrollment on the door device (backend:
// DeviceEnrollmentController). Clicking puts the device into scan mode for
// this person; they then scan at the device. `enroll({ type, fingerId })`
// does the request. `disabledReason` (if any) explains why it can't be used yet.
// `loadStatus()` fetches what is registered so far (EnrollmentStatus on the
// backend); while a scan is still waiting it's re-checked every few seconds.
const POLL_MS = 5000
const POLL_LIMIT = 24 // ~2 minutes

export default function EnrollOnDevice({ enroll, disabledReason, loadStatus }) {
  const [fingerId, setFingerId] = useState('6')
  const [busy, setBusy] = useState(null) // 'finger' | 'face' | null
  const [result, setResult] = useState(null) // { ok, message }
  const [status, setStatus] = useState({ state: 'loading', enrollments: [], refreshError: null })
  const [polls, setPolls] = useState(0)

  const refresh = useCallback(async () => {
    try {
      const data = await loadStatus()
      setStatus({ state: 'ready', enrollments: data.enrollments, refreshError: data.refreshError })
    } catch {
      setStatus((prev) => ({ ...prev, state: 'error' }))
    }
    // loadStatus is recreated by the parent on every render; the person it loads for doesn't change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    refresh()
  }, [refresh])

  const waiting = status.enrollments.some((e) => e.status === 'pending')
  useEffect(() => {
    if (!waiting || polls >= POLL_LIMIT) return
    const timer = setTimeout(() => {
      setPolls((n) => n + 1)
      refresh()
    }, POLL_MS)
    return () => clearTimeout(timer)
  }, [waiting, polls, refresh])

  const run = async (type) => {
    setBusy(type)
    setResult(null)
    try {
      const response = await enroll(type === 'finger' ? { type, fingerId: Number(fingerId) } : { type })
      setResult({ ok: true, message: response.message })
      setPolls(0)
      refresh()
    } catch (err) {
      const fieldError = Object.values(err.errors || {})[0]?.[0]
      setResult({ ok: false, message: fieldError || err.message || 'Could not reach the door device service.' })
    } finally {
      setBusy(null)
    }
  }

  const disabled = Boolean(disabledReason) || busy !== null

  return (
    <div className="sm:col-span-2 space-y-3">
      <EnrollmentList status={status} onRefresh={refresh} />
      <div className="flex flex-wrap items-center gap-2">
        <Select
          aria-label="Finger"
          value={fingerId}
          onChange={(e) => setFingerId(e.target.value)}
          disabled={disabled}
          className="w-auto!"
        >
          {Array.from({ length: 10 }, (_, i) => (
            <option key={i} value={i}>
              Finger {i}
            </option>
          ))}
        </Select>
        <Button type="button" variant="secondary" onClick={() => run('finger')} disabled={disabled}>
          {busy === 'finger' ? 'Sending…' : 'Enroll fingerprint'}
        </Button>
        <Button type="button" variant="secondary" onClick={() => run('face')} disabled={disabled}>
          {busy === 'face' ? 'Sending…' : 'Enroll face'}
        </Button>
      </div>
      <p className="text-xs text-[color:var(--color-ink-faint)]">
        {disabledReason ||
          'Finger numbers follow the device’s own numbering. After clicking, the person scans at the device.'}
      </p>
      {result && (
        <p
          role="status"
          className={`rounded-md px-3 py-2 text-sm ${
            result.ok
              ? 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-dark)]'
              : 'bg-[color:var(--color-danger-soft)] text-[color:var(--color-danger)]'
          }`}
        >
          {result.message}
        </p>
      )}
    </div>
  )
}

const STATUS_STYLE = {
  registered: 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-dark)]',
  pending: 'bg-[color:var(--color-amber-soft)] text-[color:var(--color-amber)]',
  failed: 'bg-[color:var(--color-danger-soft)] text-[color:var(--color-danger)]',
}

function describe(e) {
  const what = e.kind === 'face' ? 'Face' : `Finger ${e.fingerId}`
  if (e.status === 'registered') return `${what} ✓`
  if (e.status === 'pending') return `${what} · waiting for scan…`
  return `${what} · failed${e.returnCode !== null ? ` (code ${e.returnCode})` : ''}`
}

function EnrollmentList({ status, onRefresh }) {
  const { state, enrollments, refreshError } = status

  return (
    <div>
      <div className="mb-1.5 flex items-center gap-2">
        <span className="text-xs text-[color:var(--color-ink-faint)]">Registered on the device</span>
        <button
          type="button"
          onClick={onRefresh}
          className="text-xs text-[color:var(--color-brand)] hover:underline"
        >
          Refresh
        </button>
      </div>
      {state === 'loading' ? (
        <p className="text-sm text-[color:var(--color-ink-soft)]">Checking the device…</p>
      ) : state === 'error' ? (
        <p className="text-sm text-[color:var(--color-danger)]">Couldn't load enrollment status.</p>
      ) : enrollments.length === 0 ? (
        <p className="text-sm text-[color:var(--color-ink-soft)]">No face or fingerprint enrolled yet.</p>
      ) : (
        <ul className="flex flex-wrap gap-1.5">
          {enrollments.map((e) => (
            <li
              key={`${e.kind}:${e.fingerId}`}
              title={e.lastAttemptAt ? `Last attempt ${new Date(e.lastAttemptAt).toLocaleString()}` : undefined}
              className={`rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_STYLE[e.status]}`}
            >
              {describe(e)}
            </li>
          ))}
        </ul>
      )}
      {refreshError && <p className="mt-1.5 text-xs text-[color:var(--color-amber)]">{refreshError}</p>}
    </div>
  )
}
