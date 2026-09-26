import { useEffect, useRef, useState } from 'react'
import { useGymSettings } from '../../context/GymSettingsContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import FormField, { TextInput, TextArea } from '../../components/shared/FormField'

// The gym's profile. Whatever is saved here is what the sidebar, sign-in
// pages, receipts and browser tab show (see BrandingContext), so changing
// the gym's name or logo never needs a code change.

const emptyForm = { name: '', tagline: '', email: '', phone: '', website: '', address: '' }

function validate(form) {
  const errors = {}
  if (!form.name.trim()) errors.name = 'Gym name is required.'
  if (form.email && !/^\S+@\S+\.\S+$/.test(form.email)) errors.email = 'Enter a valid email address.'
  return errors
}

export default function GymSettingsPage() {
  const { settings, status, save } = useGymSettings()
  const [form, setForm] = useState(emptyForm)
  const [logoFile, setLogoFile] = useState(null)
  const [logoPreview, setLogoPreview] = useState(null)
  const [removeLogo, setRemoveLogo] = useState(false)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const fileInputRef = useRef(null)

  useEffect(() => {
    if (settings) {
      setForm(Object.fromEntries(Object.keys(emptyForm).map((key) => [key, settings[key] || ''])))
    }
  }, [settings])

  // Preview a newly picked logo before it's saved.
  useEffect(() => {
    if (!logoFile) {
      setLogoPreview(null)
      return
    }
    const url = URL.createObjectURL(logoFile)
    setLogoPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [logoFile])

  const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }))

  const clearFileInput = () => {
    setLogoFile(null)
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const found = validate(form)
    setErrors(found)
    if (Object.keys(found).length) return

    setSaving(true)
    try {
      await save({ ...form, logoFile, removeLogo })
      clearFileInput()
      setRemoveLogo(false)
    } catch (err) {
      // Laravel validation errors come back as { field: [messages] }.
      const serverErrors = Object.fromEntries(
        Object.entries(err.errors || {}).map(([field, messages]) => [field, messages[0]])
      )
      setErrors(Object.keys(serverErrors).length ? serverErrors : { name: err.message })
    } finally {
      setSaving(false)
    }
  }

  if (status === 'loading') {
    return <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading gym settings…</div>
  }

  const shownLogo = logoPreview || (!removeLogo && settings?.logoUrl)

  return (
    <div className="max-w-2xl">
      <PageHeader
        back={{ to: '/setup', label: 'Back to setup' }}
        title="Gym Settings"
        description="Your gym's name, tagline, contact details and logo. These appear on the sidebar, sign-in pages and receipts."
      />
      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-5" noValidate>
          <FormField label="Logo" htmlFor="logo" error={errors.logo} hint="PNG, JPG, GIF or WebP, up to 2MB. A square image works best.">
            <div className="flex items-center gap-4">
              <div className="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-lg border border-[color:var(--color-line)] bg-[color:var(--color-paper)]">
                {shownLogo ? (
                  <img src={shownLogo} alt="Gym logo" className="h-full w-full object-contain" />
                ) : (
                  <span className="text-xs text-[color:var(--color-ink-faint)]">No logo</span>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {/* The native file input only knows about a newly picked file
                    ("No file chosen" even when a logo is saved), so it's
                    hidden behind a button that reflects the real state. */}
                <input
                  id="logo"
                  ref={fileInputRef}
                  type="file"
                  accept="image/png,image/jpeg,image/gif,image/webp"
                  onChange={(e) => {
                    setLogoFile(e.target.files?.[0] || null)
                    setRemoveLogo(false)
                  }}
                  className="sr-only"
                />
                <Button variant="secondary" onClick={() => fileInputRef.current?.click()}>
                  {settings?.logoUrl || logoFile ? 'Change logo' : 'Upload logo'}
                </Button>
                {logoFile && (
                  <span className="max-w-[12rem] truncate text-xs text-[color:var(--color-ink-soft)]" title={logoFile.name}>
                    {logoFile.name}
                  </span>
                )}
                {logoFile ? (
                  <Button variant="ghost" onClick={clearFileInput}>
                    Cancel
                  </Button>
                ) : (
                  settings?.logoUrl &&
                  !removeLogo && (
                    <Button variant="danger" onClick={() => setRemoveLogo(true)}>
                      Remove logo
                    </Button>
                  )
                )}
                {removeLogo && (
                  <span className="text-xs text-[color:var(--color-ink-soft)]">
                    Logo will be removed when you save.{' '}
                    <button type="button" className="underline" onClick={() => setRemoveLogo(false)}>
                      Undo
                    </button>
                  </span>
                )}
              </div>
            </div>
          </FormField>

          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <FormField label="Gym name" htmlFor="name" required error={errors.name}>
              <TextInput id="name" value={form.name} onChange={update('name')} error={errors.name} />
            </FormField>
            <FormField label="Tagline" htmlFor="tagline" error={errors.tagline} hint="Short line shown under the name.">
              <TextInput
                id="tagline"
                value={form.tagline}
                onChange={update('tagline')}
                placeholder="e.g. Train hard, live strong"
                error={errors.tagline}
              />
            </FormField>
            <FormField label="Email" htmlFor="email" error={errors.email}>
              <TextInput id="email" type="email" value={form.email} onChange={update('email')} error={errors.email} />
            </FormField>
            <FormField label="Phone" htmlFor="phone" error={errors.phone}>
              <TextInput id="phone" value={form.phone} onChange={update('phone')} error={errors.phone} />
            </FormField>
            <FormField label="Website" htmlFor="website" error={errors.website}>
              <TextInput
                id="website"
                value={form.website}
                onChange={update('website')}
                placeholder="e.g. www.yourgym.lk"
                error={errors.website}
              />
            </FormField>
          </div>

          <FormField label="Address" htmlFor="address" error={errors.address}>
            <TextArea id="address" rows={2} value={form.address} onChange={update('address')} error={errors.address} />
          </FormField>

          <div className="flex justify-end pt-2 border-t border-[color:var(--color-line)]">
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save settings'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}
