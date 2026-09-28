import { BrowserRouter, Routes, Route, Outlet } from 'react-router-dom'
import { ThemeProvider } from './context/ThemeContext'
import { BrandingProvider } from './context/BrandingContext'
import { ToastProvider } from './context/ToastContext'
import { AuthProvider } from './context/AuthContext'
import { MembersProvider } from './context/MembersContext'
import { PaymentsProvider } from './context/PaymentsContext'
import { PaymentPlansProvider } from './context/PaymentPlansContext'
import { UsersProvider } from './context/UsersContext'
import { RolesProvider } from './context/RolesContext'
import { BranchesProvider } from './context/BranchesContext'
import { GymSettingsProvider } from './context/GymSettingsContext'
import { EmailSettingsProvider } from './context/EmailSettingsContext'
import { BackupsProvider } from './context/BackupsContext'
import RequireAuth from './routes/RequireAuth'
import RequirePermission from './routes/RequirePermission'
import AppLayout from './layout/SidePanel'

import LoginPage from './pages/auth/LoginPage'
import SignupPage from './pages/auth/SignupPage'
import MemberHomePage from './pages/members/MemberHomePage'
import DashboardPage from './pages/DashboardPage'

import MemberListPage from './pages/members/MemberListPage'
import MemberFormPage from './pages/members/MemberFormPage'
import MemberDetailPage from './pages/members/MemberDetailPage'
import MemberAttendencePage from './pages/members/MemberAttendencePage'
import StaffAttendancePage from './pages/members/StaffAttendancePage'
import GuestListPage from './pages/Guest/GuestListPage'
import GuestDetailPage from './pages/Guest/GuestDetailPage'

import PaymentsDashboardPage from './pages/payments/PaymentsDashboardPage'
import MembershipListPage from './pages/payments/membership/MembershipListPage'
import MembershipFormPage from './pages/payments/membership/MembershipFormPage'
import MembershipDetailPage from './pages/payments/membership/MembershipDetailPage'

import SetupPage from './pages/setup/SetupPage'
import UsersListPage from './pages/setup/UsersListPage'
import UserFormPage from './pages/setup/UserFormPage'
import RolesListPage from './pages/setup/RolesListPage'
import RoleFormPage from './pages/setup/RoleFormPage'
import PaymentPlansPage from './pages/setup/PaymentPlansPage'
import PaymentPlanFormPage from './pages/setup/PaymentPlanFormPage'
import BranchesListPage from './pages/setup/BranchesListPage'
import BranchFormPage from './pages/setup/BranchFormPage'
import GymSettingsPage from './pages/setup/GymSettingsPage'
import EmailSettingsPage from './pages/setup/EmailSettingsPage'
import LoginActivityPage from './pages/setup/LoginActivityPage'
import SystemDiagnosticsPage from './pages/setup/SystemDiagnosticsPage'
import BackupsPage from './pages/setup/BackupsPage'

// Members/Payments providers wrap every authenticated page (Dashboard reads
// from them too). Setup's own providers (Users/Roles/Branches/...) are
// scoped to just the /setup/* subtree below so a front-desk-only user's
// session never fires requests against endpoints they can't reach.
function CoreProviders() {
  return (
    <MembersProvider>
      <PaymentsProvider>
        <PaymentPlansProvider>
          <Outlet />
        </PaymentPlansProvider>
      </PaymentsProvider>
    </MembersProvider>
  )
}

function SetupProviders() {
  return (
    <UsersProvider>
      <RolesProvider>
        <BranchesProvider>
          <GymSettingsProvider>
            <EmailSettingsProvider>
              <BackupsProvider>
                <Outlet />
              </BackupsProvider>
            </EmailSettingsProvider>
          </GymSettingsProvider>
        </BranchesProvider>
      </RolesProvider>
    </UsersProvider>
  )
}

export default function App() {
  return (
    <ThemeProvider>
      <BrandingProvider>
        <ToastProvider>
          <AuthProvider>
            <BrowserRouter>
              <Routes>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/signup" element={<SignupPage />} />

                {/* Member area — signed-in members and guests (staff are sent to /).
                    Same side-panel layout as staff, without the staff data
                    providers (member tokens can't call those endpoints). */}
                <Route element={<RequireAuth account="member" />}>
                  <Route element={<AppLayout />}>
                    <Route path="/member" element={<MemberHomePage />} />
                  </Route>
                </Route>

                <Route element={<RequireAuth />}>
                  <Route element={<CoreProviders />}>
                    <Route element={<AppLayout />}>
                      <Route path="/" element={<DashboardPage />} />

                      <Route path="/members/all" element={<MemberListPage />} />
                      <Route path="/members/new" element={<MemberFormPage />} />
                      <Route path="/members/:memberId" element={<MemberDetailPage />} />
                      <Route path="/members/:memberId/edit" element={<MemberFormPage />} />
                      <Route path="/members/guests" element={<GuestListPage />} />
                      <Route path="/members/guests/:guestId" element={<GuestDetailPage />} />
                      <Route path="/members/attendence" element={<MemberAttendencePage />} />
                      <Route path="/members/staff-attendance" element={<StaffAttendancePage />} />

                      <Route path="/payments/summary" element={<PaymentsDashboardPage />} />
                      <Route path="/payments/membership" element={<MembershipListPage />} />
                      <Route path="/payments/membership/new" element={<MembershipFormPage />} />
                      <Route path="/payments/membership/:transactionId" element={<MembershipDetailPage />} />

                      <Route element={<SetupProviders />}>
                        <Route element={<RequirePermission />}>
                          <Route path="/setup" element={<SetupPage />} />

                          <Route path="/setup/users" element={<UsersListPage />} />
                          <Route path="/setup/users/new" element={<UserFormPage />} />
                          <Route path="/setup/users/:userId/edit" element={<UserFormPage />} />

                          <Route path="/setup/roles" element={<RolesListPage />} />
                          <Route path="/setup/roles/new" element={<RoleFormPage />} />
                          <Route path="/setup/roles/:roleId/edit" element={<RoleFormPage />} />

                          <Route path="/setup/payment-plans" element={<PaymentPlansPage />} />
                          <Route path="/setup/payment-plans/new" element={<PaymentPlanFormPage />} />
                          <Route path="/setup/payment-plans/:planId/edit" element={<PaymentPlanFormPage />} />

                          <Route path="/setup/branches" element={<BranchesListPage />} />
                          <Route path="/setup/branches/new" element={<BranchFormPage />} />
                          <Route path="/setup/branches/:branchId/edit" element={<BranchFormPage />} />

                          <Route path="/setup/gym" element={<GymSettingsPage />} />
                          <Route path="/setup/email" element={<EmailSettingsPage />} />
                          <Route path="/setup/login-activity" element={<LoginActivityPage />} />
                          <Route path="/setup/diagnostics" element={<SystemDiagnosticsPage />} />
                          <Route path="/setup/backups" element={<BackupsPage />} />
                        </Route>
                      </Route>

                      <Route path="*" element={<NotFound />} />
                    </Route>
                  </Route>
                </Route>
              </Routes>
            </BrowserRouter>
          </AuthProvider>
        </ToastProvider>
      </BrandingProvider>
    </ThemeProvider>
  )
}

function NotFound() {
  return (
    <div className="py-16 text-center">
      <p className="font-display text-xl font-semibold text-[color:var(--color-ink)]">Page not found</p>
      <p className="mt-1 text-sm text-[color:var(--color-ink-soft)]">
        Check the URL, or use the sidebar to get back on track.
      </p>
    </div>
  )
}
