// Single source of truth for the sidebar + route permission gating.
// Plain-JS analogue of a typed navigationTree — each node names the
// permission key required to see/use it. Mirrors the canonical key list in
// the backend's App\Support\Permissions::ALL.
//
// Consumed by:
//   src/layout/SidePanel.jsx      — generic recursive nav renderer
//   src/routes/RequirePermission.jsx — per-route gating via getPermissionForPath()

import { BsGrid, BsPeopleFill, BsGearFill } from 'react-icons/bs'
import { FaRegCreditCard, FaPeopleRobbery, FaBoxOpen } from 'react-icons/fa6'
import { SiMealie } from "react-icons/si";


// A node with `children` is a dropdown only: clicking it opens its list and
// goes nowhere, so it carries `match` (the URL prefix its section lives under,
// used for highlighting and breadcrumbs) instead of a navigable `path`.
export const NAVIGATION_TREE = [
  { label: 'Dashboard', path: '/', end: true, icon: BsGrid, permission: 'dashboard.view' },
  {
    label: 'Payment',
    match: '/payments',
    icon: FaRegCreditCard,
    permission: 'payments.view',
    children: [
      { label: 'Payment Summary', path: '/payments/summary', permission: 'payments.view' },
      { label: 'Membership Payment', path: '/payments/membership', permission: 'payments.view' },
      { label: 'Stock Payments', path: '/payments/Stock/payments', permission: 'payments.view' },
      { label: 'Stock Sales', path: '/payments/Stock/sales', permission: 'payments.view' },
      { label: 'Monthly Subscription', path: '/payments/subscription', permission: 'payments.view' },
      { label: 'Audit', path: '/payments/Audit', permission: 'payments_audit.view' }
    ],
  },
  {
    label: 'Members',
    match: '/members',
    icon: BsPeopleFill,
    permission: 'members.view',
    children: [
      { label: 'All Members', path: '/members/all', permission: 'members.view' },
      { label: 'Guests', path: '/members/guests', permission: 'members.view' },
      { label: 'Attendence', path: '/members/attendence', permission: 'attendance.view' },
      { label: 'Staff Attendance', path: '/members/staff-attendance', permission: 'attendance.view' },
      { label: 'Member Review', path: '/members/review', permission: 'members.review' }
    ],
  },
  {
    label: 'Inventory',
    match: '/inventory',
    icon: FaBoxOpen,
    permission: 'inventory.view',
    children: [
      { label: 'Stock', path: '/stock', permission: 'stock.view' },
      { label: 'Dealers', path: '/inventory/dealers', permission: 'dealers.view' },
      { label: 'Audit', path: '/inventory/audit', permission: 'inventory_audit.view' }
    ],
  },
  {
    label: 'Exercise Plan',
    path: '/exercisePlan',
    icon: FaPeopleRobbery,
    permission: 'exercise_plan.view',
  },
  {
    label: 'Meal Plan',
    path: '/mealPlan',
    icon: SiMealie,
    permission: 'meal_plan.view',
  },
  {
    label: 'Setup',
    path: '/setup',
    icon: BsGearFill,
    permission: 'setup.manage',
    // No `children` — Setup is a plain sidebar link to the /setup tiles
    // page (SetupPage.jsx) rather than an expandable dropdown. Its former
    // children now live in SETUP_TILES below, rendered as tiles instead.
  },
]

/**
 * Sidebar for signed-in members and guests (the /member area). They have no
 * permissions, so these items carry none — SidePanel shows them as-is.
 */
export const MEMBER_NAVIGATION_TREE = [
  { label: 'Dashboard', path: '/member', end: true, icon: BsGrid },
]

/**
 * Setup's sections, rendered as tiles on SetupPage.jsx instead of a sidebar
 * dropdown. Kept separate from NAVIGATION_TREE since these are no longer
 * sidebar nodes, just page content — but getPermissionForPath's existing
 * '/setup/*' fallback (below) already covers their permission gating, and
 * getPageMeta cross-references this list for navbar titles/breadcrumbs.
 */
export const SETUP_TILES = [
  { label: 'Users', path: '/setup/users', permission: 'setup.manage', description: 'Manage staff accounts' },
  { label: 'Roles', path: '/setup/roles', permission: 'setup.manage', description: 'Define permission sets' },
  { label: 'Payment Plans', path: '/setup/payment-plans', permission: 'setup.manage', description: 'Set membership plans and prices' },
  { label: 'Branches', path: '/setup/branches', permission: 'setup.manage', description: 'Manage gym locations' },
  { label: 'Gym Settings', path: '/setup/gym', permission: 'setup.manage', description: 'General gym configuration' },
  { label: 'Email Setup', path: '/setup/email', permission: 'setup.manage', description: 'Configure email delivery' },
  { label: 'Login Activity', path: '/setup/login-activity', permission: 'setup.manage', description: 'Staff sign-in history' },
  { label: 'Diagnostics', path: '/setup/diagnostics', permission: 'setup.manage', description: 'Check system health' },
  { label: 'Backups', path: '/setup/backups', permission: 'setup.manage', description: 'Manage database backups' },
]

function flatten(nodes) {
  return nodes.flatMap((node) => [node, ...(node.children ? flatten(node.children) : [])])
}

/**
 * The URL prefix a node owns: its own `path` for links, or its `match` for
 * dropdown parents that have no page of their own. Doubles as a stable React
 * key and as the identity of the currently-open sidebar section.
 */
export function navPrefix(node) {
  return node.path ?? node.match
}

/**
 * Finds the permission key required for an exact route path. Falls back to
 * 'setup.manage' for any unlisted /setup/* sub-route (e.g. /setup/users/new,
 * /setup/roles/:id/edit) so per-entity form/detail routes don't need their
 * own tree entries.
 */
export function getPermissionForPath(path) {
  const match = flatten(NAVIGATION_TREE).find((node) => node.path === path)
  if (match) return match.permission
  if (path.startsWith('/setup')) return 'setup.manage'
  return null
}

/**
 * Derives a { title, breadcrumb } pair for the top navbar from the current
 * route, reusing NAVIGATION_TREE as the single source of truth instead of a
 * separate hardcoded route→title map. Exact matches win; otherwise the
 * longest tree path that prefixes the route wins, so untracked child routes
 * (e.g. /members/:id, /members/new) still resolve to their section (e.g.
 * "Members"). Falls back to Dashboard for anything unmatched (shouldn't
 * happen for routes reachable from the sidebar).
 */
export function getPageMeta(path) {
  const flat = flatten(NAVIGATION_TREE)

  const exact = flat.find((node) => node.path === path)
  const treeMatch =
    exact ||
    flat
      .filter((node) => navPrefix(node) !== '/' && path.startsWith(navPrefix(node)))
      .sort((a, b) => navPrefix(b).length - navPrefix(a).length)[0]

  // SETUP_TILES isn't part of NAVIGATION_TREE (Setup has no children
  // anymore), so it's checked separately — whichever match has the longer,
  // more specific path wins, same longest-prefix rule as above.
  const tileMatch = SETUP_TILES.filter((tile) => path.startsWith(tile.path)).sort(
    (a, b) => b.path.length - a.path.length
  )[0]

  const useTileMatch = tileMatch && (!treeMatch || tileMatch.path.length > navPrefix(treeMatch).length)

  if (useTileMatch) {
    return { title: tileMatch.label, breadcrumb: `Setup / ${tileMatch.label}` }
  }

  if (!treeMatch) return { title: 'Dashboard', breadcrumb: 'Home' }

  const parent = NAVIGATION_TREE.find(
    (node) => node !== treeMatch && node.children?.includes(treeMatch)
  )

  return {
    title: treeMatch.label,
    breadcrumb: parent ? `${parent.label} / ${treeMatch.label}` : treeMatch.label,
  }
}
