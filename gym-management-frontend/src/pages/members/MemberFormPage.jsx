import { useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMembers } from '../../context/MembersContext'
import { usePaymentPlans } from '../../context/PaymentPlansContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import FormField, { TextInput, TextArea, Select } from '../../components/shared/FormField'
import { todayISO, formatPlan } from '../../utils/format'
import { kgToLb, lbToKg, cmToFtIn, ftInToCm, round } from '../../utils/units'

const emptyForm = {
  memberIdNumber: '',
  fullName: '',
  nic: '',
  dob: '',
  email: '',
  phone: '',
  whatsappNumber: '',
  gender: '',
  address: '',
  joinDate: todayISO(),
  paymentPlanId: '',
  weightKg: '',
  heightCm: '',
  occupation: '',
  emergencyContactName: '',
  emergencyContactPhone: '',
  username: '',
  password: '',
  passwordConfirmation: '',
  status: 'Active',
  notes: '',
}

// Password is only ever collected at creation time — editing an existing
// member never shows or touches it (mirrors UserFormPage/UpdateUserRequest's
// "password changes are a deliberate, separate action" pattern).
function validate(form, isEdit) {
  const errors = {}
  if (!form.fullName.trim()) errors.fullName = 'Full name is required.'
  if (form.email.trim() && !/^\S+@\S+\.\S+$/.test(form.email)) {
    errors.email = 'Enter a valid email address.'
  }
  if (!form.dob) errors.dob = 'Date of birth is required.'
  if (!form.phone.trim()) errors.phone = 'Phone number is required.'
  if (!form.whatsappNumber.trim()) errors.whatsappNumber = 'WhatsApp number is required.'
  if (!form.gender) errors.gender = 'Gender is required.'
  if (!form.paymentPlanId) errors.paymentPlanId = 'Select a payment plan.'
  if (form.weightKg === '' || form.weightKg == null) errors.weightKg = 'Weight is required.'
  if (form.heightCm === '' || form.heightCm == null) errors.heightCm = 'Height is required.'
  if (!form.username.trim()) errors.username = 'Preferred username is required.'

  if (!isEdit) {
    if (!form.password) {
      errors.password = 'Password is required.'
    } else if (form.password.length < 8) {
      errors.password = 'Password must be at least 8 characters.'
    }
    if (!form.passwordConfirmation) {
      errors.passwordConfirmation = 'Please confirm the password.'
    } else if (form.password !== form.passwordConfirmation) {
      errors.passwordConfirmation = 'Passwords do not match.'
    }
  }

  return errors
}

// Blank out nulls — members created before WhatsApp/weight/height/username
// were added have null there, which breaks .trim() in validate() and
// controlled inputs.
function toFormShape(record) {
  return {
    ...emptyForm,
    ...Object.fromEntries(Object.entries(record).map(([key, value]) => [key, value ?? ''])),
  }
}

/**
 * The member form itself, shared by:
 *   - MemberFormPage below (staff: add/edit member)
 *   - SignupPage (public self-signup, variant="signup")
 *
 * variant="signup" hides the fields only staff should set (member ID number,
 * join date, status, notes). `onSubmit(values)` does the save; if it throws
 * an ApiError, its field errors are shown inline and the message in a banner.
 */
export function MemberForm({
  initialValues,
  isEdit = false,
  variant = 'staff',
  planOptions,
  noPlansHint,
  onSubmit,
  onCancel,
  submitLabel = 'Save member',
  savingLabel = 'Saving…',
}) {
  const isSignup = variant === 'signup'

  const baseline = useMemo(() => (initialValues ? toFormShape(initialValues) : emptyForm), [initialValues])
  const [form, setForm] = useState(baseline)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState('')

  const [weightUnit, setWeightUnit] = useState('kg')
  const [heightUnit, setHeightUnit] = useState('cm')

  const update = (field) => (e) => {
    const value = e?.target ? e.target.value : e
    setForm((f) => ({ ...f, [field]: value }))
  }

  const hasWeight = form.weightKg !== '' && form.weightKg != null
  const displayWeight = hasWeight
    ? round(weightUnit === 'kg' ? Number(form.weightKg) : kgToLb(Number(form.weightKg)), 1)
    : ''

  const handleWeightChange = (e) => {
    const raw = e.target.value
    if (raw === '') {
      setForm((f) => ({ ...f, weightKg: '' }))
      return
    }
    const num = parseFloat(raw)
    if (Number.isNaN(num)) return
    setForm((f) => ({ ...f, weightKg: weightUnit === 'kg' ? num : lbToKg(num) }))
  }

  const hasHeight = form.heightCm !== '' && form.heightCm != null
  const displayHeightCm = hasHeight ? round(Number(form.heightCm), 1) : ''
  const { ft: currentFt, inch: currentInch } = hasHeight
    ? cmToFtIn(Number(form.heightCm))
    : { ft: '', inch: '' }
  const displayFt = hasHeight ? Math.floor(currentFt) : ''
  const displayIn = hasHeight ? round(currentInch, 1) : ''

  const handleHeightCmChange = (e) => {
    const raw = e.target.value
    if (raw === '') {
      setForm((f) => ({ ...f, heightCm: '' }))
      return
    }
    const num = parseFloat(raw)
    if (Number.isNaN(num)) return
    setForm((f) => ({ ...f, heightCm: num }))
  }

  const handleHeightFtChange = (e) => {
    const raw = e.target.value
    const ftVal = raw === '' ? 0 : parseFloat(raw)
    if (Number.isNaN(ftVal)) return
    setForm((f) => ({ ...f, heightCm: ftInToCm(ftVal, typeof currentInch === 'number' ? currentInch : 0) }))
  }

  const handleHeightInChange = (e) => {
    const raw = e.target.value
    const inchVal = raw === '' ? 0 : parseFloat(raw)
    if (Number.isNaN(inchVal)) return
    setForm((f) => ({ ...f, heightCm: ftInToCm(typeof currentFt === 'number' ? currentFt : 0, inchVal) }))
  }

  const isDirty = useMemo(
    () => JSON.stringify(form) !== JSON.stringify(baseline),
    [form, baseline]
  )

  const handleCancel = () => {
    if (isDirty && !window.confirm('Discard unsaved changes?')) return
    onCancel()
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = validate(form, isEdit)
    setErrors(validationErrors)
    setFormError('')
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      // Password is create-only; staff-only fields never leave a signup form.
      const { password, passwordConfirmation, ...rest } = form
      const values = isEdit ? rest : { ...rest, password, passwordConfirmation }
      if (isSignup) {
        delete values.memberIdNumber
        delete values.joinDate
        delete values.status
        delete values.notes
      }
      await onSubmit(values)
    } catch (err) {
      // Show the server's validation errors on their fields (keys arrive
      // camelCased from apiClient); anything else goes in the banner.
      if (err.errors) {
        setErrors(Object.fromEntries(Object.entries(err.errors).map(([field, messages]) => [field, messages[0]])))
      }
      setFormError(err.message || 'Could not save the member.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      {formError && (
        <p
          role="alert"
          className="rounded-md bg-[color:var(--color-danger-soft)] px-3 py-2 text-sm text-[color:var(--color-danger)]"
        >
          {formError}
        </p>
      )}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
        {!isSignup && (
          <FormField label="Member ID number" htmlFor="memberIdNumber">
            <TextInput
              id="memberIdNumber"
              value={form.memberIdNumber}
              onChange={update('memberIdNumber')}
              placeholder="e.g. card/badge number"
            />
          </FormField>
        )}
        <FormField label="Full name" htmlFor="fullName" required error={errors.fullName}>
          <TextInput
            id="fullName"
            value={form.fullName}
            onChange={update('fullName')}
            error={errors.fullName}
            placeholder="Jane Doe"
          />
        </FormField>
        <FormField label="NIC" htmlFor="nic">
          <TextInput id="nic" value={form.nic} onChange={update('nic')} placeholder="National ID card number" />
        </FormField>
        <FormField label="Date of birth" htmlFor="dob" required error={errors.dob}>
          <TextInput id="dob" type="date" value={form.dob} onChange={update('dob')} error={errors.dob} />
        </FormField>
        <FormField label="Email" htmlFor="email" error={errors.email}>
          <TextInput
            id="email"
            type="email"
            value={form.email}
            onChange={update('email')}
            error={errors.email}
            placeholder="jane@example.com"
          />
        </FormField>
        <FormField label="Phone" htmlFor="phone" required error={errors.phone}>
          <TextInput
            id="phone"
            value={form.phone}
            onChange={update('phone')}
            error={errors.phone}
            placeholder="077 123 4567"
          />
        </FormField>
        <FormField label="WhatsApp number" htmlFor="whatsappNumber" required error={errors.whatsappNumber}>
          <TextInput
            id="whatsappNumber"
            value={form.whatsappNumber}
            onChange={update('whatsappNumber')}
            error={errors.whatsappNumber}
            placeholder="077 123 4567"
          />
        </FormField>
        <FormField label="Gender" htmlFor="gender" required error={errors.gender}>
          <Select id="gender" value={form.gender} onChange={update('gender')} error={errors.gender}>
            <option value="">Select…</option>
            <option value="Female">Female</option>
            <option value="Male">Male</option>
            <option value="Other">Other</option>
          </Select>
        </FormField>
        {!isSignup && (
          <FormField label="Join date" htmlFor="joinDate">
            <TextInput id="joinDate" type="date" value={form.joinDate} onChange={update('joinDate')} />
          </FormField>
        )}
        <FormField
          label="Payment plan"
          htmlFor="paymentPlanId"
          required
          error={errors.paymentPlanId}
          hint={planOptions.length === 0 ? noPlansHint : undefined}
        >
          <Select
            id="paymentPlanId"
            value={form.paymentPlanId}
            onChange={update('paymentPlanId')}
            error={errors.paymentPlanId}
          >
            <option value="">Select a plan…</option>
            {planOptions.map((plan) => (
              <option key={plan.id} value={plan.id}>
                {formatPlan(plan)}
                {plan.isActive ? '' : ' (retired)'}
              </option>
            ))}
          </Select>
        </FormField>
      </div>

      <FormField label="Address" htmlFor="address">
        <TextInput id="address" value={form.address} onChange={update('address')} placeholder="Street, city" />
      </FormField>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <FormField label="Weight" htmlFor="weightKg" required error={errors.weightKg}>
          <div className="flex gap-2">
            <TextInput
              id="weightKg"
              type="number"
              step="0.1"
              min="0"
              value={displayWeight}
              onChange={handleWeightChange}
              error={errors.weightKg}
              className="flex-1"
            />
            <Select
              aria-label="Weight unit"
              value={weightUnit}
              onChange={(e) => setWeightUnit(e.target.value)}
              className="w-24!"
            >
              <option value="kg">kg</option>
              <option value="lb">lb</option>
            </Select>
          </div>
        </FormField>

        <FormField label="Height" htmlFor="heightCm" required error={errors.heightCm}>
          <div className="flex gap-2">
            {heightUnit === 'cm' ? (
              <TextInput
                id="heightCm"
                type="number"
                step="0.1"
                min="0"
                value={displayHeightCm}
                onChange={handleHeightCmChange}
                error={errors.heightCm}
                className="flex-1"
              />
            ) : (
              <div className="flex flex-1 gap-2">
                <TextInput
                  type="number"
                  step="1"
                  min="0"
                  aria-label="Feet"
                  value={displayFt}
                  onChange={handleHeightFtChange}
                  error={errors.heightCm}
                  placeholder="ft"
                />
                <TextInput
                  type="number"
                  step="0.1"
                  min="0"
                  aria-label="Inches"
                  value={displayIn}
                  onChange={handleHeightInChange}
                  error={errors.heightCm}
                  placeholder="in"
                />
              </div>
            )}
            <Select
              aria-label="Height unit"
              value={heightUnit}
              onChange={(e) => setHeightUnit(e.target.value)}
              className="w-24!"
            >
              <option value="cm">cm</option>
              <option value="ft">ft/in</option>
            </Select>
          </div>
        </FormField>
      </div>

      <FormField label="Occupation" htmlFor="occupation">
        <TextInput id="occupation" value={form.occupation} onChange={update('occupation')} />
      </FormField>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <FormField label="Emergency contact name" htmlFor="emergencyContactName">
          <TextInput
            id="emergencyContactName"
            value={form.emergencyContactName}
            onChange={update('emergencyContactName')}
          />
        </FormField>
        <FormField label="Emergency contact phone" htmlFor="emergencyContactPhone">
          <TextInput
            id="emergencyContactPhone"
            value={form.emergencyContactPhone}
            onChange={update('emergencyContactPhone')}
          />
        </FormField>
      </div>

      <div className="space-y-5 pt-2 border-t border-[color:var(--color-line)]">
        <FormField label="Preferred username" htmlFor="username" required error={errors.username}>
          <TextInput
            id="username"
            value={form.username}
            onChange={update('username')}
            error={errors.username}
            autoComplete="off"
          />
        </FormField>

        {!isEdit && (
          <>
            <FormField label="Password" htmlFor="password" required error={errors.password}>
              <TextInput
                id="password"
                type="password"
                value={form.password}
                onChange={update('password')}
                error={errors.password}
                autoComplete="new-password"
              />
            </FormField>
            <FormField
              label="Confirm password"
              htmlFor="passwordConfirmation"
              required
              error={errors.passwordConfirmation}
            >
              <TextInput
                id="passwordConfirmation"
                type="password"
                value={form.passwordConfirmation}
                onChange={update('passwordConfirmation')}
                error={errors.passwordConfirmation}
                autoComplete="new-password"
              />
            </FormField>
          </>
        )}
      </div>

      {isEdit && (
        <FormField label="Status" htmlFor="status">
          <Select id="status" value={form.status} onChange={update('status')}>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Suspended">Suspended</option>
          </Select>
        </FormField>
      )}

      {!isSignup && (
        <FormField label="Notes" htmlFor="notes">
          <TextArea id="notes" rows={3} value={form.notes} onChange={update('notes')} placeholder="Optional" />
        </FormField>
      )}

      <div className="flex justify-end gap-2 pt-2 border-t border-[color:var(--color-line)]">
        <Button type="button" variant="secondary" onClick={handleCancel} disabled={saving}>
          Cancel
        </Button>
        <Button type="submit" disabled={saving}>
          {saving ? savingLabel : submitLabel}
        </Button>
      </div>
    </form>
  )
}

export default function MemberFormPage() {
  const { memberId } = useParams()
  const isEdit = Boolean(memberId)
  const navigate = useNavigate()
  const { getMemberById, addMember, editMember } = useMembers()
  const { activePlans, getPlanById } = usePaymentPlans()

  const existing = isEdit ? getMemberById(memberId) : null

  // New members can only go on a current plan; an existing member may keep
  // the plan they're on even after it's been retired.
  const currentPlan = existing?.paymentPlanId ? getPlanById(existing.paymentPlanId) : null
  const planOptions =
    currentPlan && !currentPlan.isActive ? [...activePlans, currentPlan] : activePlans

  const handleSubmit = async (values) => {
    if (isEdit) {
      await editMember(memberId, values)
      navigate(`/members/${memberId}`)
    } else {
      const created = await addMember(values)
      navigate(`/members/${created.id}`)
    }
  }

  if (isEdit && !existing) {
    return (
      <Card className="p-8 text-center text-sm text-[color:var(--color-ink-soft)]">
        Member not found.
      </Card>
    )
  }

  return (
    <div className="max-w-2xl">
      <PageHeader
        title={isEdit ? 'Edit member' : 'Add member'}
        description={isEdit ? `Update ${existing.fullName}'s profile.` : 'Create a new member profile.'}
        back={{ to: '/members', label: 'Back to members' }}
      />

      <Card className="p-6">
        <MemberForm
          initialValues={existing}
          isEdit={isEdit}
          planOptions={planOptions}
          noPlansHint="No plans set up yet — add one in Setup → Payment Plans."
          onSubmit={handleSubmit}
          onCancel={() => navigate(isEdit ? `/members/${memberId}` : '/members')}
        />
      </Card>
    </div>
  )
}
