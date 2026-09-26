import { useEffect, useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { useBranding } from '../../context/BrandingContext'
import { HOME_FOR } from '../../routes/RequireAuth'
import * as paymentPlanService from '../../services/paymentPlanService'
import AuthShell from '../../components/auth/AuthShell'
import { MemberForm } from '../members/MemberFormPage'

// Public member self-signup. Uses the same MemberForm as staff "Add member"
// (variant="signup" hides staff-only fields), posts to /api/auth/signup and
// signs the new member straight in — see AuthController::signup.
export default function SignupPage() {
  const { status, user, signup } = useAuth()
  const { branding } = useBranding()
  const navigate = useNavigate()

  const [plans, setPlans] = useState([])
  const [plansStatus, setPlansStatus] = useState('loading') // loading | ready | error

  useEffect(() => {
    paymentPlanService
      .listPublicPaymentPlans()
      .then((data) => {
        // Public endpoint only returns current plans; flag them as such so
        // MemberForm doesn't label them "(retired)".
        setPlans(data.map((plan) => ({ ...plan, isActive: true })))
        setPlansStatus('ready')
      })
      .catch(() => setPlansStatus('error'))
  }, [])

  if (status === 'ready' && user) {
    return <Navigate to={HOME_FOR[user.accountType || 'staff']} replace />
  }

  const handleSubmit = async (values) => {
    await signup(values)
    navigate(HOME_FOR.member, { replace: true })
  }

  return (
    <AuthShell
      wide
      heading={`Join ${branding.name}`}
      blurb="Create your membership account in a few minutes. Pick a plan, tell us a little about yourself, and you're ready to train."
      subtitle="Create your member account"
    >
      <MemberForm
        variant="signup"
        planOptions={plans}
        noPlansHint={
          plansStatus === 'loading'
            ? 'Loading plans…'
            : plansStatus === 'error'
              ? "Couldn't load plans. Refresh the page, or ask at the front desk."
              : 'No plans are available right now — please ask at the front desk.'
        }
        onSubmit={handleSubmit}
        onCancel={() => navigate('/login')}
        submitLabel="Create account"
        savingLabel="Creating account…"
      />

      <p className="mt-6 text-center text-sm text-[color:var(--color-ink-soft)]">
        Already have an account?{' '}
        <Link to="/login" className="font-medium text-[color:var(--color-brand)] hover:underline">
          Sign in
        </Link>
      </p>
    </AuthShell>
  )
}
