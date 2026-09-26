import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { FiLogOut } from 'react-icons/fi'
import { useAuth } from '../../context/AuthContext'
import { useBranding } from '../../context/BrandingContext'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import ThemeToggle from '../../components/shared/ThemeToggle'

// Placeholder landing page for signed-in members (/member). The member area
// hasn't been designed yet — this just confirms they're signed in.
export default function MemberHomePage() {
  const { user, logout } = useAuth()
  const { branding } = useBranding()
  const navigate = useNavigate()
  const [signingOut, setSigningOut] = useState(false)

  const handleSignOut = async () => {
    setSigningOut(true)
    try {
      await logout()
      navigate('/login', { replace: true })
    } finally {
      setSigningOut(false)
    }
  }

  return (
    <div className="brand-maroon relative min-h-screen flex items-center justify-center bg-[color:var(--color-paper)] px-6 py-12">
      <ThemeToggle className="absolute right-4 top-4" />
      <Card className="w-full max-w-md p-8 text-center">
        <img src={branding.logoUrl} alt={`${branding.name} logo`} className="h-14 w-auto mx-auto mb-6" />
        <h1 className="font-display text-2xl font-semibold text-[color:var(--color-ink)]">
          Welcome, {user?.fullName?.split(' ')[0] || 'member'}!
        </h1>
        <p className="mt-2 text-sm text-[color:var(--color-ink-soft)]">
          You're signed in{user?.username ? <> as <span className="font-medium">{user.username}</span></> : null}.
          {user?.paymentPlan && <> Your plan: <span className="font-medium">{user.paymentPlan}</span>.</>}
        </p>
        <p className="mt-4 text-sm text-[color:var(--color-ink-soft)]">
          More member features are coming soon. For anything else, please visit the front desk.
        </p>
        <Button variant="secondary" className="mt-8" onClick={handleSignOut} disabled={signingOut}>
          <FiLogOut />
          {signingOut ? 'Signing out…' : 'Sign out'}
        </Button>
      </Card>
    </div>
  )
}
