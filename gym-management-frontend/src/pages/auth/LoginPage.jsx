import { useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { FiUser, FiLock, FiEye, FiEyeOff, FiArrowRight } from 'react-icons/fi'
import { useAuth } from '../../context/AuthContext'
import { useBranding } from '../../context/BrandingContext'
import { HOME_FOR } from '../../routes/RequireAuth'
import Button from '../../components/shared/Button'
import FormField, { TextInput } from '../../components/shared/FormField'
import AuthShell from '../../components/auth/AuthShell'

// One sign-in for everyone: the username/password from registration. The
// backend decides where they land — staff (an admin granted their
// registration a role) or member. See AuthController::login.

export default function LoginPage() {
  const { status, user, login } = useAuth()
  const { branding } = useBranding()
  const navigate = useNavigate()
  const location = useLocation()

  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const redirectTo = location.state?.from || '/'

  // Already signed in (or just signed in): send each account type home;
  // staff go back to the page that bounced them here, if any.
  if (status === 'ready' && user) {
    return <Navigate to={user.accountType === 'member' ? HOME_FOR.member : redirectTo} replace />
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      const signedIn = await login(identifier, password)
      navigate(signedIn.accountType === 'member' ? HOME_FOR.member : redirectTo, { replace: true })
    } catch (err) {
      setError(err.message || 'Login failed.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthShell
      heading="Welcome Back"
      blurb={`Manage memberships, payments, and staff all in one place. Sign in to keep ${branding.name} running smoothly.`}
      subtitle="Sign in to your account to continue"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <FormField label="Username" htmlFor="identifier" required>
          <div className="relative">
            <FiUser className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[color:var(--color-ink-faint)]" />
            <TextInput
              id="identifier"
              type="text"
              autoFocus
              autoComplete="username"
              value={identifier}
              onChange={(e) => setIdentifier(e.target.value)}
              placeholder="Your username"
              className="pl-9"
              required
            />
          </div>
        </FormField>

        <FormField label="Password" htmlFor="password" required error={error}>
          <div className="relative">
            <FiLock className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[color:var(--color-ink-faint)]" />
            <TextInput
              id="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="pl-9 pr-9"
              required
            />
            <button
              type="button"
              onClick={() => setShowPassword((v) => !v)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-[color:var(--color-ink-faint)] hover:text-[color:var(--color-ink-soft)]"
              aria-label={showPassword ? 'Hide password' : 'Show password'}
            >
              {showPassword ? <FiEyeOff /> : <FiEye />}
            </button>
          </div>
        </FormField>

        <Button type="submit" className="w-full" disabled={submitting}>
          {submitting ? (
            'Signing in…'
          ) : (
            <>
              Sign in
              <FiArrowRight />
            </>
          )}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-[color:var(--color-ink-soft)]">
        Don't have an account?{' '}
        <Link to="/signup" className="font-medium text-[color:var(--color-brand)] hover:underline">
          Register
        </Link>
      </p>
      <p className="mt-2 text-center text-xs text-[color:var(--color-ink-faint)]">
        Staff: register first, then an administrator will grant your access.
      </p>
    </AuthShell>
  )
}
