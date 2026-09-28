import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { useBranding } from '../../context/BrandingContext'
import { HOME_FOR } from '../../routes/RequireAuth'
import AuthShell from '../../components/auth/AuthShell'
import Button from '../../components/shared/Button'
import FormField, { TextInput } from '../../components/shared/FormField'

// Public self-signup. Everyone registers as a Guest with just these details
// and is signed straight in; staff make them a member (plan, door PIN) from
// Members > Guests. Staff accounts also start here — an admin then grants a
// role in Setup > Users. Backend: AuthController::signup / SignupRequest.

const emptyForm = {
  fullName: '',
  phone: '',
  whatsappNumber: '',
  dob: '',
  username: '',
  password: '',
  passwordConfirmation: '',
}

const today = () => new Date().toISOString().slice(0, 10)

function validate(form) {
  const errors = {}
  if (!form.fullName.trim()) errors.fullName = 'Full name is required.'
  if (!form.phone.trim()) errors.phone = 'Phone number is required.'
  if (!form.whatsappNumber.trim()) errors.whatsappNumber = 'WhatsApp number is required.'
  if (!form.dob) errors.dob = 'Date of birth is required.'
  else if (form.dob >= today()) errors.dob = 'Date of birth must be in the past.'
  if (!form.username.trim()) errors.username = 'Username is required.'
  if (!form.password) errors.password = 'Password is required.'
  else if (form.password.length < 8) errors.password = 'Password must be at least 8 characters.'
  if (!form.passwordConfirmation) errors.passwordConfirmation = 'Please confirm the password.'
  else if (form.password !== form.passwordConfirmation) errors.passwordConfirmation = 'Passwords do not match.'
  return errors
}

export default function SignupPage() {
  const { status, user, signup } = useAuth()
  const { branding } = useBranding()
  const navigate = useNavigate()

  const [form, setForm] = useState(emptyForm)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  if (status === 'ready' && user) {
    return <Navigate to={HOME_FOR[user.accountType || 'staff']} replace />
  }

  const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }))

  // Most people's WhatsApp is their phone number — copy it until they change it.
  const updatePhone = (e) => {
    const phone = e.target.value
    setForm((f) => ({ ...f, phone, whatsappNumber: f.whatsappNumber === f.phone ? phone : f.whatsappNumber }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = validate(form)
    setErrors(validationErrors)
    setFormError('')
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      await signup({ ...form, fullName: form.fullName.trim(), username: form.username.trim() })
      navigate(HOME_FOR.member, { replace: true })
    } catch (err) {
      if (err.errors) {
        setErrors(Object.fromEntries(Object.entries(err.errors).map(([field, messages]) => [field, messages[0]])))
      }
      setFormError(err.message || 'Could not create your account.')
      setSaving(false)
    }
  }

  return (
    <AuthShell
      heading={`Join ${branding.name}`}
      blurb="Register in a minute. Then visit the front desk to choose a plan and activate your membership."
      subtitle="Create your account"
    >
      <form onSubmit={handleSubmit} noValidate className="space-y-4">
        <FormField label="Full name" htmlFor="fullName" required error={errors.fullName}>
          <TextInput id="fullName" value={form.fullName} onChange={update('fullName')} error={errors.fullName} autoComplete="name" />
        </FormField>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField label="Phone" htmlFor="phone" required error={errors.phone}>
            <TextInput id="phone" type="tel" value={form.phone} onChange={updatePhone} error={errors.phone} autoComplete="tel" />
          </FormField>
          <FormField label="WhatsApp" htmlFor="whatsappNumber" required error={errors.whatsappNumber}>
            <TextInput
              id="whatsappNumber"
              type="tel"
              value={form.whatsappNumber}
              onChange={update('whatsappNumber')}
              error={errors.whatsappNumber}
            />
          </FormField>
        </div>

        <FormField label="Date of birth" htmlFor="dob" required error={errors.dob}>
          <TextInput id="dob" type="date" max={today()} value={form.dob} onChange={update('dob')} error={errors.dob} />
        </FormField>

        <FormField label="Username" htmlFor="username" required error={errors.username} hint="You'll use this to sign in.">
          <TextInput id="username" value={form.username} onChange={update('username')} error={errors.username} autoComplete="username" />
        </FormField>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField label="Password" htmlFor="password" required error={errors.password}>
            <TextInput
              id="password"
              type="password"
              value={form.password}
              onChange={update('password')}
              error={errors.password}
              autoComplete="new-password"
            />
          </FormField>
          <FormField label="Confirm password" htmlFor="passwordConfirmation" required error={errors.passwordConfirmation}>
            <TextInput
              id="passwordConfirmation"
              type="password"
              value={form.passwordConfirmation}
              onChange={update('passwordConfirmation')}
              error={errors.passwordConfirmation}
              autoComplete="new-password"
            />
          </FormField>
        </div>

        {formError && Object.keys(errors).length === 0 && (
          <p className="text-sm text-[color:var(--color-danger)]">{formError}</p>
        )}

        <Button type="submit" className="w-full justify-center" disabled={saving}>
          {saving ? 'Creating account…' : 'Create account'}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-[color:var(--color-ink-soft)]">
        Already have an account?{' '}
        <Link to="/login" className="font-medium text-[color:var(--color-brand)] hover:underline">
          Sign in
        </Link>
      </p>
    </AuthShell>
  )
}
