import { Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { getPermissionForPath } from '../config/navigationTree'
import ForbiddenPage from '../pages/ForbiddenPage'

/**
 * Route-level permission gate. Pass `permission` explicitly for a route
 * whose path isn't in navigationTree.js verbatim (e.g. /setup/users/new);
 * otherwise it's looked up from the current path.
 */
export default function RequirePermission({ permission }) {
  const { hasPermission } = useAuth()
  const location = useLocation()

  const required = permission || getPermissionForPath(location.pathname)

  if (required && !hasPermission(required)) {
    return <ForbiddenPage />
  }

  return <Outlet />
}
