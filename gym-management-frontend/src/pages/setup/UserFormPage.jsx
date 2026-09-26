import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useUsers } from '../../context/UsersContext'
import { useRoles } from '../../context/RolesContext'
import { useBranches } from '../../context/BranchesContext'
import * as userService from '../../services/userService'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import Avatar from '../../components/shared/Avatar'
import Combobox from '../../components/shared/Combobox'
import FormField, { TextInput, Select } from '../../components/shared/FormField'

// Add = grant a role to someone who has already registered on /signup (their
// name, email, username and password come from that registration). Edit =
// change a staff account's role, branch or status. Accounts from before
// registrations existed (e.g. the bootstrap admin) have no registration, so
// their own name/email stay editable here.

const emptyForm = { memberId: '', fullName: '', email: '', roleId: '', branchId: '', status: 'Active' }

function validate(form, { isEdit, isLinked }) {
  const errors = {}
  if (!isEdit && !form.memberId) errors.memberId = 'Select a registered person.'
  if (!isEdit && !form.roleId) errors.roleId = 'Select a role.'
  if (isEdit && !isLinked) {
    if (!form.fullName.trim()) errors.fullName = 'Full name is required.'
    if (!form.email.trim()) errors.email = 'Email is required.'
    else if (!/^\S+@\S+\.\S+$/.test(form.email)) errors.email = 'Enter a valid email address.'
  }
  return errors
}

export default function UserFormPage() {
  const { userId } = useParams()
  const isEdit = Boolean(userId)
  const navigate = useNavigate()
  const { getUserById, addUser, editUser, resetPassword } = useUsers()
  const { roles } = useRoles()
  const { branches } = useBranches()

  const existing = isEdit ? getUserById(Number(userId)) : null
  const isLinked = Boolean(existing?.memberId)
  const toFormShape = (record) => ({
    ...emptyForm,
    fullName: record.fullName || '',
    email: record.email || '',
    roleId: record.roleId ?? '',
    branchId: record.branchId ?? '',
    status: record.status,
  })
  const [form, setForm] = useState(() => (existing ? toFormShape(existing) : emptyForm))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [newPassword, setNewPassword] = useState('')
  const [resetting, setResetting] = useState(false)

  // Registered people who aren't staff yet (only needed when granting).
  const [candidates, setCandidates] = useState([])
  const [candidatesStatus, setCandidatesStatus] = useState(isEdit ? 'ready' : 'loading')
  useEffect(() => {
    if (isEdit) return
    userService
      .listGrantCandidates()
      .then((list) => {
        setCandidates(list)
        setCandidatesStatus('ready')
      })
      .catch(() => setCandidatesStatus('error'))
  }, [isEdit])

  const candidateOptions = useMemo(
    () => candidates.map((c) => ({ value: String(c.id), label: `${c.fullName} (@${c.username})` })),
    [candidates]
  )
  const pickedCandidate = candidates.find((c) => String(c.id) === form.memberId)

  const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }))

  const baseline = useMemo(() => (isEdit && existing ? toFormShape(existing) : emptyForm), [isEdit, existing])
  const isDirty = useMemo(() => JSON.stringify(form) !== JSON.stringify(baseline), [form, baseline])

  const handleCancel = () => {
    if (isDirty && !window.confirm('Discard unsaved changes?')) return
    navigate('/setup/users')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = validate(form, { isEdit, isLinked })
    setErrors(validationErrors)
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      const access = { roleId: form.roleId || null, branchId: form.branchId || null }
      if (!isEdit) {
        await addUser({ memberId: Number(form.memberId), ...access })
      } else {
        await editUser(Number(userId), {
          ...access,
          status: form.status,
          ...(isLinked ? {} : { fullName: form.fullName, email: form.email }),
        })
      }
      navigate('/setup/users')
    } catch (err) {
      const serverErrors = Object.fromEntries(
        Object.entries(err.errors || {}).map(([field, messages]) => [field, messages[0]])
      )
      setErrors(Object.keys(serverErrors).length ? serverErrors : { memberId: err.message })
    } finally {
      setSaving(false)
    }
  }

  const handleResetPassword = async () => {
    if (newPassword.length < 8) {
      setErrors((e) => ({ ...e, newPassword: 'Password must be at least 8 characters.' }))
      return
    }
    setResetting(true)
    try {
      await resetPassword(Number(userId), newPassword)
      setNewPassword('')
      setErrors((e) => ({ ...e, newPassword: undefined }))
    } finally {
      setResetting(false)
    }
  }

  // Name / username / email summary, shown read-only for registrations.
  const identity = isEdit ? (isLinked ? existing : null) : pickedCandidate
  const identityCard = identity && (
    <div className="flex items-center gap-3 rounded-md border border-[color:var(--color-line)] bg-[color:var(--color-paper)] px-3 py-2.5">
      <Avatar name={identity.fullName} size="sm" />
      <div className="min-w-0 text-sm">
        <div className="truncate font-medium text-[color:var(--color-ink)]">{identity.fullName}</div>
        <div className="truncate text-xs text-[color:var(--color-ink-faint)]">
          {[`@${identity.username}`, identity.email].filter(Boolean).join(' · ')}
        </div>
      </div>
    </div>
  )

  return (
    <div className="max-w-lg space-y-6">
      <div>
        <PageHeader
          back={{ to: '/setup/users', label: 'Back to users' }}
          title={isEdit ? 'Edit user' : 'Grant role'}
          description={
            isEdit
              ? 'Change what this staff member can access.'
              : 'Give someone who has registered on the sign-up page a staff role. They sign in with their own username and password.'
          }
        />
        <Card className="p-6">
          <form onSubmit={handleSubmit} className="space-y-5">
            {!isEdit && (
              <FormField
                label="Registered person"
                htmlFor="memberId"
                required
                error={errors.memberId}
                hint={
                  candidatesStatus === 'ready' && candidates.length === 0
                    ? 'No one is waiting — ask them to register on the sign-up page first.'
                    : 'Type a name or username to search.'
                }
              >
                <Combobox
                  id="memberId"
                  value={form.memberId}
                  onChange={(memberId) => setForm((f) => ({ ...f, memberId }))}
                  options={candidateOptions}
                  placeholder={candidatesStatus === 'loading' ? 'Loading registrations…' : 'Search registered people…'}
                  emptyMessage="No registered person matches"
                  error={errors.memberId}
                  disabled={candidatesStatus !== 'ready'}
                />
              </FormField>
            )}

            {identityCard}

            {isEdit && !isLinked && (
              <>
                <FormField label="Full name" htmlFor="fullName" required error={errors.fullName}>
                  <TextInput id="fullName" value={form.fullName} onChange={update('fullName')} error={errors.fullName} />
                </FormField>
                <FormField label="Email" htmlFor="email" required error={errors.email} hint="Used to sign in (this account has no registration).">
                  <TextInput id="email" type="email" value={form.email} onChange={update('email')} error={errors.email} />
                </FormField>
              </>
            )}

            <FormField label="Role" htmlFor="roleId" required={!isEdit} error={errors.roleId}>
              <Select id="roleId" value={form.roleId} onChange={update('roleId')} error={errors.roleId}>
                <option value="">{isEdit ? 'No role' : 'Select a role…'}</option>
                {roles.map((role) => (
                  <option key={role.id} value={role.id}>
                    {role.name}
                  </option>
                ))}
              </Select>
            </FormField>
            <FormField label="Branch" htmlFor="branchId" error={errors.branchId}>
              <Select id="branchId" value={form.branchId} onChange={update('branchId')}>
                <option value="">No branch</option>
                {branches.map((branch) => (
                  <option key={branch.id} value={branch.id}>
                    {branch.name}
                  </option>
                ))}
              </Select>
            </FormField>
            {isEdit && (
              <FormField label="Status" htmlFor="status">
                <Select id="status" value={form.status} onChange={update('status')}>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                </Select>
              </FormField>
            )}

            <div className="flex justify-end gap-2 pt-2 border-t border-[color:var(--color-line)]">
              <Button type="button" variant="secondary" onClick={handleCancel} disabled={saving}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : isEdit ? 'Save user' : 'Grant role'}
              </Button>
            </div>
          </form>
        </Card>
      </div>

      {isEdit && (
        <div>
          <h2 className="font-display text-base font-semibold text-[color:var(--color-ink)] mb-2">
            Reset password
          </h2>
          <Card className="p-6">
            <FormField
              label="New password"
              htmlFor="newPassword"
              error={errors.newPassword}
              hint="Changes the password they sign in with. They'll be signed out everywhere."
            >
              <TextInput
                id="newPassword"
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                error={errors.newPassword}
              />
            </FormField>
            <div className="flex justify-end pt-4">
              <Button type="button" variant="secondary" onClick={handleResetPassword} disabled={resetting}>
                {resetting ? 'Resetting…' : 'Reset password'}
              </Button>
            </div>
          </Card>
        </div>
      )}
    </div>
  )
}
