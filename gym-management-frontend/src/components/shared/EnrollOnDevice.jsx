import { useState } from 'react'
import Button from './Button'
import { Select } from './FormField'

// Fingerprint / face enrollment on the door device (backend:
// DeviceEnrollmentController). Clicking puts the device into scan mode for
// this person; they then scan at the device. `enroll({ type, fingerId })`
// does the request. `disabledReason` (if any) explains why it can't be used yet.
export default function EnrollOnDevice({ enroll, disabledReason }) {
  const [fingerId, setFingerId] = useState('6')
  const [busy, setBusy] = useState(null) // 'finger' | 'face' | null
  const [result, setResult] = useState(null) // { ok, message }

  const run = async (type) => {
    setBusy(type)
    setResult(null)
    try {
      const response = await enroll(type === 'finger' ? { type, fingerId: Number(fingerId) } : { type })
      setResult({ ok: true, message: response.message })
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
