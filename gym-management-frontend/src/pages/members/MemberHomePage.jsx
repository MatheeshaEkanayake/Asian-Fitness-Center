import { useAuth } from '../../context/AuthContext'
import { useBranding } from '../../context/BrandingContext'
import Card from '../../components/shared/Card'

// Dashboard for signed-in members and guests (/member), shown inside the
// side-panel layout (SidePanel.jsx). Guests see a "visit the front desk"
// notice; members see their plan. The member area hasn't been designed
// further yet.
export default function MemberHomePage() {
  const { user } = useAuth()
  const { branding } = useBranding()
  const isGuest = user?.status === 'Guest'

  return (
    <div className="flex min-h-[calc(100dvh-10rem)] items-center justify-center">
      <Card className="w-full max-w-md p-8 text-center">
        <img src={branding.logoUrl} alt={`${branding.name} logo`} className="h-14 w-auto mx-auto mb-6" />
        <h1 className="font-display text-2xl font-semibold text-[color:var(--color-ink)]">
          Welcome, {user?.fullName?.split(' ')[0] || 'member'}!
        </h1>
        <p className="mt-2 text-sm text-[color:var(--color-ink-soft)]">
          You're signed in{user?.username ? <> as <span className="font-medium">{user.username}</span></> : null}.
          {!isGuest && user?.paymentPlan && <> Your plan: <span className="font-medium">{user.paymentPlan}</span>.</>}
        </p>

        {isGuest ? (
          <div className="mt-6 rounded-md border border-[color:var(--color-line)] bg-[color:var(--color-amber-soft)] px-4 py-3 text-left text-sm text-[color:var(--color-ink)]">
            <p className="font-medium">You're registered as a guest.</p>
            <p className="mt-1 text-[color:var(--color-ink-soft)]">
              Visit the front desk to choose a plan and activate your membership.
            </p>
          </div>
        ) : (
          <p className="mt-4 text-sm text-[color:var(--color-ink-soft)]">
            More member features are coming soon. For anything else, please visit the front desk.
          </p>
        )}
      </Card>
    </div>
  )
}
