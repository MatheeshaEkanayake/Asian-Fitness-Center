import { useEffect, useState } from 'react'
import { usePaymentPlans } from '../../context/PaymentPlansContext'
import { useMembers } from '../../context/MembersContext'
import { useToast } from '../../context/ToastContext'
import * as guestService from '../../services/guestService'
import { formatPlan } from '../../utils/format'
import Modal from '../../components/shared/Modal'
import Button from '../../components/shared/Button'
import FormField, { TextInput, Select } from '../../components/shared/FormField'

// Makes a guest an Active member: pick a plan, optionally set their door PIN.
// Used by GuestListPage and GuestDetailPage. Backend: GuestController::promote.
export default function MakeMemberDialog({ guest, onClose, onPromoted }) {
  const { activePlans } = usePaymentPlans()
  const { reload: reloadMembers } = useMembers()
  const { showToast } = useToast()

  const [planId, setPlanId] = useState('')
  const [memberIdNumber, setMemberIdNumber] = useState('')
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    setPlanId('')
    setMemberIdNumber(guest?.memberIdNumber || '')
    setErrors({})
  }, [guest])

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = {}
    if (!planId) validationErrors.paymentPlanId = 'Select a payment plan.'
    if (memberIdNumber.trim() && !/^\d{1,9}$/.test(memberIdNumber.trim())) {
      validationErrors.memberIdNumber = 'Member ID number must be 1–9 digits (it is the door PIN).'
    }
    setErrors(validationErrors)
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      const member = await guestService.promoteGuest(guest.id, {
        paymentPlanId: Number(planId),
        memberIdNumber: memberIdNumber.trim() || null,
      })
      showToast(`${member.fullName} is now a member.`)
      await reloadMembers() // so their member profile is there to open
      onPromoted(member)
    } catch (err) {
      const serverErrors = Object.fromEntries(
        Object.entries(err.errors || {}).map(([field, messages]) => [field, messages[0]])
      )
      setErrors(Object.keys(serverErrors).length ? serverErrors : { paymentPlanId: err.message })
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal open={Boolean(guest)} onClose={saving ? () => {} : onClose} title="Make member" width="max-w-md">
      <form onSubmit={handleSubmit} className="space-y-4">
        <p className="text-sm text-[color:var(--color-ink-soft)]">
          <span className="font-medium text-[color:var(--color-ink)]">{guest?.fullName}</span> becomes an
          active member joining today. Door access starts once a payment is recorded.
        </p>

        <FormField
          label="Payment plan"
          htmlFor="promotePlan"
          required
          error={errors.paymentPlanId}
          hint={activePlans.length === 0 ? 'No active plans — add one in Setup › Payment Plans.' : undefined}
        >
          <Select id="promotePlan" value={planId} onChange={(e) => setPlanId(e.target.value)} error={errors.paymentPlanId}>
            <option value="">Select a plan…</option>
            {activePlans.map((plan) => (
              <option key={plan.id} value={plan.id}>
                {formatPlan(plan)}
              </option>
            ))}
          </Select>
        </FormField>

        <FormField
          label="Member ID number (door PIN)"
          htmlFor="promotePin"
          error={errors.memberIdNumber}
          hint="Optional. Digits only (1–9) — can also be added later from their profile."
        >
          <TextInput
            id="promotePin"
            inputMode="numeric"
            value={memberIdNumber}
            onChange={(e) => setMemberIdNumber(e.target.value)}
            placeholder="e.g. 1024"
            error={errors.memberIdNumber}
          />
        </FormField>

        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="secondary" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" disabled={saving}>
            {saving ? 'Saving…' : 'Make member'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
