import { useEffect, useRef, useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { BsChevronDown } from 'react-icons/bs'
import { FiMenu, FiChevronLeft, FiX, FiLogOut } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useBranding } from '../context/BrandingContext'
import { NAVIGATION_TREE, MEMBER_NAVIGATION_TREE, getPageMeta, navPrefix } from '../config/navigationTree'
import { initials } from '../utils/format'
import ConfirmDialog from '../components/shared/ConfirmDialog'
import ThemeToggle from '../components/shared/ThemeToggle'

function NavItem({ item, hasPermission, collapsed, onNavigate, isOpen, onToggleOpen, onOpenSection, onExpandSidebar, isActiveSection, onActivateSection, anySectionActive, onSelectStandalone }) {
  const visibleChildren = (item.children || []).filter((child) => !child.permission || hasPermission(child.permission))
  const hasChildren = visibleChildren.length > 0

  if (item.permission && !hasPermission(item.permission)) return null

  const rowClasses = (active) =>
    `group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${
      collapsed ? 'justify-center px-2' : ''
    } ${
      active
        ? 'bg-white/10 text-white shadow-[inset_3px_0_0_0_var(--color-brand)]'
        : 'text-white/65 hover:bg-white/5 hover:text-white'
    }`

  const iconWrap = (active) => (
    <span
      className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border transition-colors ${
        active
          ? 'border-[color:var(--color-brand)]/40 bg-[color:var(--color-brand)]/25 text-white'
          : 'border-white/5 bg-white/5 text-white/70 group-hover:text-white'
      }`}
    >
      {item.icon && <item.icon className="h-4 w-4" />}
    </span>
  )

  // Collapsed rail: a parent has no page to open and no room for its list, so
  // its icon widens the sidebar back out with that section expanded. Leaf
  // items stay real NavLinks so ctrl/middle-click still works.
  if (collapsed && hasChildren) {
    return (
      <div className="mb-1">
        <button
          type="button"
          title={item.label}
          aria-expanded={false}
          onClick={() => {
            onExpandSidebar?.()
            onOpenSection()
            onActivateSection()
          }}
          className={({ isActive }) => rowClasses(isActive && !anySectionActive)}
        >
          {({ isActive }) => (
            <>
              {iconWrap(isActive && !anySectionActive)}
              {!collapsed && item.label}
            </>
          )}
        </button>
      </div>
    )
  }

  if (!hasChildren) {
    return (
      <div className="mb-1">
        <NavLink
          to={item.path}
          end={item.end}
          title={collapsed ? item.label : undefined}
          onClick={() => {
            onSelectStandalone()
            onNavigate?.()
          }}
          className={({ isActive }) => rowClasses(isActive && !anySectionActive)}
        >
          {({ isActive }) => (
            <>
              {iconWrap(isActive && !anySectionActive)}
              {!collapsed && item.label}
            </>
          )}
        </NavLink>
      </div>
    )
  }

  return (
    <div className="mb-1">
      <button
        type="button"
        aria-expanded={isOpen}
        onClick={() => {
          onToggleOpen()
          onActivateSection()
        }}
        className={rowClasses(isActiveSection)}
      >
        {iconWrap(isActiveSection)}
        <span className="flex-1 text-left">{item.label}</span>
        <BsChevronDown className={`h-3.5 w-3.5 text-white/40 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>
      {isOpen && (
        <div className="ml-4 mt-1 space-y-0.5 border-l border-white/10 pl-4">
          {visibleChildren.map((child) => (
            <NavLink
              key={child.path}
              to={child.path}
              onClick={() => onNavigate?.()}
              className={({ isActive }) =>
                `block rounded-lg px-3 py-1.5 text-sm transition-colors ${
                  isActive ? 'text-white bg-white/10' : 'text-white/50 hover:text-white hover:bg-white/5'
                }`
              }
            >
              {child.label}
            </NavLink>
          ))}
        </div>
      )}
    </div>
  )
}

// Shared between the always-mounted desktop rail (width-animated) and the
// mobile slide-over drawer (translate-animated) so both stay visually and
// behaviorally identical. `collapsed` only ever applies to the desktop rail.
function SidebarContent({
  collapsed,
  navigation,
  hasPermission,
  user,
  roleLabel,
  onLogoutClick,
  showBrandToggle,
  brandToggleIcon,
  onBrandToggle,
  onNavigate,
  onExpandSidebar,
}) {
  const location = useLocation()
  const { branding } = useBranding()

  // Which section the current route falls under, if any — the starting
  // point for both which list is expanded and which parent shows active.
  const sectionForPath = (pathname) => {
    const match = navigation.find(
      (item) =>
        item.children?.length &&
        (pathname.startsWith(navPrefix(item)) || item.children.some((child) => pathname.startsWith(child.path)))
    )
    return match ? navPrefix(match) : null
  }

  // Only one parent's children are shown at a time (accordion).
  const [openKey, setOpenKey] = useState(() => sectionForPath(location.pathname))

  // Which parent button reads as "active". Unlike openKey, this doesn't
  // reset when a section's list is collapsed — it only changes when another
  // parent is clicked or navigation lands somewhere else (including a
  // standalone item, which clears it). Clicking a parent button doesn't
  // navigate by itself, so that click also has to set this directly —
  // route-based derivation alone would miss it.
  const [activeKey, setActiveKey] = useState(() => sectionForPath(location.pathname))
  useEffect(() => {
    setActiveKey(sectionForPath(location.pathname))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.pathname])

  return (
    <div className="relative flex h-full flex-col overflow-hidden bg-gradient-to-b from-[#0b1220] via-[#1b1f23] to-[#0b1324] text-white">
      <div
        className="pointer-events-none absolute inset-0 opacity-10"
        style={{
          backgroundImage:
            'radial-gradient(circle at 15% 10%, white 0, transparent 45%), radial-gradient(circle at 85% 90%, white 0, transparent 40%)',
        }}
      />

      <div className={`relative z-10 flex items-center gap-3 border-b border-white/10 px-4 py-5 ${collapsed ? 'justify-center px-2' : ''}`}>
        <img
          src={branding.logoUrl}
          alt={`${branding.name} logo`}
          className="h-10 w-10 shrink-0 rounded-xl bg-white/95 object-contain p-1"
        />
        {!collapsed && (
          <div className="min-w-0 flex-1">
            <p className="truncate font-display text-sm font-bold tracking-tight">{branding.name}</p>
            <p className="text-[0.625rem] font-semibold uppercase tracking-wider text-white/45">{branding.tagline}</p>
          </div>
        )}
        {showBrandToggle && !collapsed && (
          <button
            type="button"
            onClick={onBrandToggle}
            className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-white/50 hover:bg-white/10 hover:text-white"
            aria-label="Collapse sidebar"
          >
            {brandToggleIcon}
          </button>
        )}
      </div>

      {!collapsed && (
        <p className="relative z-10 px-4 pb-1 pt-4 text-[0.625rem] font-bold uppercase tracking-widest text-white/35">
          Main navigation
        </p>
      )}

      {/* Nav and footer share one scroll area. The footer is sticky, so nav
          items scroll underneath it, but it still takes up space at the end
          of the list — the last item can always be scrolled clear of it. */}
      <div className="relative z-10 flex min-h-0 flex-1 flex-col overflow-y-auto">
        <nav className="flex-1 px-2.5 pt-2 pb-3">
          {navigation.map((item) => (
            <NavItem
              key={navPrefix(item)}
              item={item}
              hasPermission={hasPermission}
              collapsed={collapsed}
              onNavigate={onNavigate}
              isOpen={openKey === navPrefix(item)}
              onToggleOpen={() =>
                setOpenKey((prev) => (prev === navPrefix(item) ? null : navPrefix(item)))
              }
              onOpenSection={() => setOpenKey(navPrefix(item))}
              onExpandSidebar={onExpandSidebar}
              isActiveSection={activeKey === navPrefix(item)}
              onActivateSection={() => setActiveKey(navPrefix(item))}
              anySectionActive={activeKey !== null}
              onSelectStandalone={() => {
                setOpenKey(null)
                setActiveKey(null)
              }}
            />
          ))}
        </nav>

        <div className="sticky bottom-0 z-20 shrink-0 border-t border-white/10 bg-[#0b1220]/85 px-3 py-3 backdrop-blur-md">
          <div
            className={`mb-2 flex items-center gap-2.5 rounded-xl border border-white/10 bg-white/5 ${
              collapsed ? 'justify-center p-2' : 'px-3 py-2.5'
            }`}
          >
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[color:var(--color-brand-soft)] text-xs font-display font-semibold text-[color:var(--color-brand-dark)]">
              {initials(user?.fullName) || '—'}
            </div>
            {!collapsed && (
              <div className="min-w-0">
                <p className="truncate text-xs font-semibold text-white">{user?.fullName || 'Unknown'}</p>
                {roleLabel && <p className="truncate text-[0.6875rem] text-white/45">{roleLabel}</p>}
              </div>
            )}
          </div>

          <button
            type="button"
            title="Sign out"
            onClick={onLogoutClick}
            className={`flex w-full items-center gap-2 rounded-xl border border-red-400/25 text-red-300 transition-colors hover:border-red-400/40 hover:bg-red-400/10 ${
              collapsed ? 'justify-center p-2' : 'px-3 py-2 text-sm font-medium'
            }`}
          >
            <FiLogOut className="shrink-0" />
            {!collapsed && <span>Sign out</span>}
          </button>
        </div>
      </div>
    </div>
  )
}

export default function AppLayout() {
  const { user, hasPermission, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const [collapsed, setCollapsed] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [logoutConfirmOpen, setLogoutConfirmOpen] = useState(false)
  const [loggingOut, setLoggingOut] = useState(false)

  // The page area is its own scroll container (not the window), so reset it
  // on navigation — otherwise a new page opens at the previous one's offset.
  const scrollAreaRef = useRef(null)
  useEffect(() => {
    scrollAreaRef.current?.scrollTo(0, 0)
  }, [location.pathname])

  // Members and guests (the /member area) share this layout with a
  // one-item menu; under their name they see "Guest"/"Member" instead of a role.
  const isMember = user?.accountType === 'member'
  const navigation = isMember ? MEMBER_NAVIGATION_TREE : NAVIGATION_TREE
  const roleLabel = isMember ? (user.status === 'Guest' ? 'Guest' : 'Member') : user?.role

  const pageMeta = getPageMeta(location.pathname)
  const dateLabel = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  })

  const handleLogout = async () => {
    setLoggingOut(true)
    try {
      await logout()
      navigate('/login', { replace: true })
    } finally {
      setLoggingOut(false)
      setLogoutConfirmOpen(false)
    }
  }

  return (
    // Pinned to the viewport height so the window itself never scrolls; only
    // the page area below the header does, keeping the sidebar and header
    // in place.
    <div className="h-dvh overflow-hidden flex bg-[color:var(--color-paper)]">
      {/* Desktop rail: always mounted, width-animated between collapsed/expanded */}
      <aside
        className={`hidden shrink-0 transition-[width] duration-300 ease-in-out md:flex md:flex-col ${
          collapsed ? 'md:w-[4.75rem]' : 'md:w-72'
        }`}
      >
        <SidebarContent
          collapsed={collapsed}
          navigation={navigation}
          hasPermission={hasPermission}
          user={user}
          roleLabel={roleLabel}
          onLogoutClick={() => setLogoutConfirmOpen(true)}
          showBrandToggle
          brandToggleIcon={<FiChevronLeft />}
          onBrandToggle={() => setCollapsed(true)}
          onExpandSidebar={() => setCollapsed(false)}
        />
      </aside>

      {/* Mobile drawer: slides in over a backdrop; never collapsed */}
      <div className="md:hidden">
        {mobileOpen && (
          <button
            type="button"
            aria-label="Close menu"
            onClick={() => setMobileOpen(false)}
            className="fixed inset-0 z-30 bg-black/50"
          />
        )}
        <div
          className={`fixed bottom-0 left-0 top-0 z-30 w-72 transform transition-transform duration-300 ease-in-out ${
            mobileOpen ? 'translate-x-0' : '-translate-x-full'
          }`}
        >
          <SidebarContent
            collapsed={false}
            navigation={navigation}
            hasPermission={hasPermission}
            user={user}
            roleLabel={roleLabel}
            onLogoutClick={() => setLogoutConfirmOpen(true)}
            showBrandToggle
            brandToggleIcon={<FiX />}
            onBrandToggle={() => setMobileOpen(false)}
            onNavigate={() => setMobileOpen(false)}
          />
        </div>
      </div>

      {/* In dark mode the always-dark sidebar is close to the page color, so
          a hairline keeps the edge visible. */}
      <div className="flex-1 flex flex-col min-w-0 md:dark:border-l md:dark:border-[color:var(--color-line)]">
        <header className="h-16 shrink-0 border-b border-[color:var(--color-line)] bg-[color:var(--color-surface)]/90 backdrop-blur-sm flex items-center justify-between gap-3 px-4 md:px-6">
          <div className="flex min-w-0 flex-1 items-center gap-3">
            <button
              type="button"
              onClick={() => setMobileOpen(true)}
              className="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-[color:var(--color-line-strong)] text-[color:var(--color-ink-soft)] hover:bg-[color:var(--color-paper)] md:hidden"
              aria-label="Open menu"
            >
              <FiMenu />
            </button>
            {collapsed && (
              <button
                type="button"
                onClick={() => setCollapsed(false)}
                className="hidden h-9 w-9 shrink-0 place-items-center rounded-lg border border-[color:var(--color-line-strong)] text-[color:var(--color-ink-soft)] hover:bg-[color:var(--color-paper)] md:grid"
                aria-label="Expand sidebar"
              >
                <FiMenu />
              </button>
            )}
            <div className="min-w-0">
              <h1 className="truncate font-display text-base font-semibold text-[color:var(--color-ink)]">
                {pageMeta.title}
              </h1>
              <p className="truncate text-xs text-[color:var(--color-ink-soft)]">
                {pageMeta.breadcrumb} · {dateLabel}
              </p>
            </div>
          </div>

          <div className="hidden shrink-0 items-center gap-1.5 rounded-full border border-[color:var(--color-line)] bg-[color:var(--color-paper)] px-3 py-1 text-xs font-medium text-[color:var(--color-ink-soft)] sm:flex">
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
            System Online
          </div>

          <ThemeToggle />

          <div className="flex shrink-0 items-center gap-2.5 rounded-full border border-[color:var(--color-line)] bg-[color:var(--color-paper)] py-1 pl-3 pr-1.5">
            <div className="hidden text-right sm:block">
              <div className="text-xs font-semibold leading-tight text-[color:var(--color-ink)]">
                {user?.fullName || 'Unknown'}
              </div>
              {roleLabel && (
                <div className="text-[0.6875rem] leading-tight text-[color:var(--color-ink-faint)]">{roleLabel}</div>
              )}
            </div>
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[color:var(--color-brand-soft)] text-xs font-display font-semibold text-[color:var(--color-brand-dark)]">
              {initials(user?.fullName) || '—'}
            </div>
          </div>
        </header>
        <div ref={scrollAreaRef} className="flex-1 overflow-y-auto">
          <main className="min-w-0 px-6 py-6 max-w-6xl w-full mx-auto">
            <Outlet />
          </main>
        </div>
      </div>

      <ConfirmDialog
        open={logoutConfirmOpen}
        onClose={() => setLogoutConfirmOpen(false)}
        onConfirm={handleLogout}
        title="Sign out?"
        description="You'll need to sign in again to access your account."
        confirmLabel="Sign out"
        tone="primary"
        loading={loggingOut}
      />
    </div>
  )
}
