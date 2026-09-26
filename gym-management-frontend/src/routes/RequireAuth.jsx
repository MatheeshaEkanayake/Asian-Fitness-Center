import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

// Home route for each account type — where someone lands if they hit the
// other type's area (e.g. a member opening a staff URL).
export const HOME_FOR = { staff: '/', member: '/member' }

/**
 * `account` says which kind of signed-in account this subtree is for:
 * 'staff' (the whole management app) or 'member' (the member area).
 */
export default function RequireAuth({ account = 'staff' }) {
  const { status, user } = useAuth()
  const location = useLocation()

  if (status === 'loading') {
    return (
      <div className="min-h-screen flex items-center justify-center text-sm text-[color:var(--color-ink-soft)]">
        Loading…
      </div>
    )
  }

  if (status === 'anonymous') {
    return <Navigate to="/login" state={{ from: location.pathname }} replace />
  }

  const accountType = user?.accountType || 'staff'
  if (accountType !== account) {
    return <Navigate to={HOME_FOR[accountType]} replace />
  }

  return <Outlet />
}
