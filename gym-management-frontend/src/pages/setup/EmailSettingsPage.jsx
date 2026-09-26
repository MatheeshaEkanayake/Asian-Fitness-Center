import { useEffect, useState } from 'react'
import { useEmailSettings } from '../../context/EmailSettingsContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import FormField, { TextInput, Select } from '../../components/shared/FormField'

const emptyForm = {
  host: '',
  port: '',
  encryption: 'tls',
  username: '',
  password: '',
  fromAddress: '',
  fromName: '',
}

export default function EmailSettingsPage() {
  const { settings, status, save, sendTest } = useEmailSettings()
  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [testTo, setTestTo] = useState('')
  const [testing, setTesting] = useState(false)

  useEffect(() => {
    if (settings) {
      setForm({
        host: settings.host || '',
        port: settings.port || '',
        encryption: settings.encryption || 'tls',
        username: settings.username || '',
        password: '',
        fromAddress: settings.fromAddress || '',
        fromName: settings.fromName || '',
      })
    }
  }, [settings])

  const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }))

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = {}
    if (!form.host.trim()) validationErrors.host = 'SMTP host is required.'
    if (!form.port) validationErrors.port = 'SMTP port is required.'
    if (!form.fromAddress.trim()) validationErrors.fromAddress = 'From address is required.'
    if (!form.fromName.trim()) validationErrors.fromName = 'From name is required.'
    setErrors(validationErrors)
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      await save({ ...form, password: form.password || undefined })
      setForm((f) => ({ ...f, password: '' }))
    } finally {
      setSaving(false)
    }
  }

  const handleTest = async () => {
    if (!testTo.trim()) return
    setTesting(true)
    try {
      await sendTest(testTo)
    } finally {
      setTesting(false)
    }
  }

  if (status === 'loading') {
    return <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading email settings…</div>
  }

  return (
    <div className="max-w-lg space-y-6">
      <div>
        <PageHeader
          back={{ to: '/setup', label: 'Back to setup' }}
          title="Email Setup"
          description="SMTP settings used for outgoing email."
        />
        <Card className="p-6">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <FormField label="SMTP host" htmlFor="host" required error={errors.host}>
                <TextInput id="host" value={form.host} onChange={update('host')} error={errors.host} placeholder="smtp.gmail.com" />
              </FormField>
              <FormField label="Port" htmlFor="port" required error={errors.port}>
                <TextInput id="port" type="number" value={form.port} onChange={update('port')} error={errors.port} placeholder="587" />
              </FormField>
            </div>
            <FormField label="Encryption" htmlFor="encryption">
              <Select id="encryption" value={form.encryption} onChange={update('encryption')}>
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
              </Select>
            </FormField>
            <FormField label="Username" htmlFor="username">
              <TextInput id="username" value={form.username} onChange={update('username')} />
            </FormField>
            <FormField label="Password" htmlFor="password" hint={settings?.hasPassword ? 'Leave blank to keep the current password.' : ''}>
              <TextInput id="password" type="password" value={form.password} onChange={update('password')} />
            </FormField>
            <FormField label="From address" htmlFor="fromAddress" required error={errors.fromAddress}>
              <TextInput id="fromAddress" type="email" value={form.fromAddress} onChange={update('fromAddress')} error={errors.fromAddress} />
            </FormField>
            <FormField label="From name" htmlFor="fromName" required error={errors.fromName}>
              <TextInput id="fromName" value={form.fromName} onChange={update('fromName')} error={errors.fromName} />
            </FormField>

            <div className="flex justify-end pt-2 border-t border-[color:var(--color-line)]">
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : 'Save settings'}
              </Button>
            </div>
          </form>
        </Card>
      </div>

      <div>
        <h2 className="font-display text-base font-semibold text-[color:var(--color-ink)] mb-2">Send a test email</h2>
        <Card className="p-6">
          <FormField label="Send to" htmlFor="testTo">
            <TextInput id="testTo" type="email" value={testTo} onChange={(e) => setTestTo(e.target.value)} placeholder="you@example.com" />
          </FormField>
          <div className="flex justify-end pt-4">
            <Button type="button" variant="secondary" onClick={handleTest} disabled={testing || !testTo.trim()}>
              {testing ? 'Sending…' : 'Send test email'}
            </Button>
          </div>
        </Card>
      </div>
    </div>
  )
}
