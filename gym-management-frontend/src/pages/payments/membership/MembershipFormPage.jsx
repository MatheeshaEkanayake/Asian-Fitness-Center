import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { usePayments } from '../../../context/PaymentsContext'
import { useMembers } from '../../../context/MembersContext'
import { usePaymentPlans } from '../../../context/PaymentPlansContext'
import PageHeader from '../../../components/shared/PageHeader'
import Card from '../../../components/shared/Card'
import Button from '../../../components/shared/Button'
import FormField, { TextInput, TextArea, Select } from '../../../components/shared/FormField'
import MemberSelect from '../../../components/shared/MemberSelect'
import { todayISO, formatPlan } from '../../../utils/format'

function validate(form) {
  const errors = {}
  if (!form.memberId) errors.memberId = 'Select a member.'
  if (!form.paymentPlanId) errors.paymentPlanId = 'Select a payment plan.'
  if (!form.amount || Number(form.amount) <= 0) errors.amount = 'Enter an amount greater than 0.'
  if (!form.method) errors.method = 'Select a payment method.'
  return errors
}

export default function MembershipFormPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { recordPayment } = usePayments()
  const { getMemberById } = useMembers()
  const { activePlans, getPlanById } = usePaymentPlans()

  // Picking a member defaults the plan to the one they're signed up on, and
  // picking a plan defaults the amount to its price (both still editable).
  const planDefaultsFor = (memberId) => {
    const plan = getMemberById(memberId)?.paymentPlan
    return plan ? { paymentPlanId: String(plan.id), amount: String(Number(plan.amount)) } : {}
  }

  const [form, setForm] = useState(() => ({
    memberId: searchParams.get('memberId') || '',
    paymentPlanId: '',
    amount: '',
    method: 'Card',
    type: 'OneTime',
    date: todayISO(),
    dueDate: '',
    status: 'Paid',
    notes: '',
    ...planDefaultsFor(searchParams.get('memberId')),
  }))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const update = (field) => (e) => {
    const value = e?.target ? e.target.value : e
    setForm((f) => ({ ...f, [field]: value }))
  }

  const handleMemberChange = (memberId) => {
    setForm((f) => ({ ...f, memberId, ...planDefaultsFor(memberId) }))
  }

  const handlePlanChange = (e) => {
    const paymentPlanId = e.target.value
    const plan = getPlanById(paymentPlanId)
    setForm((f) => ({ ...f, paymentPlanId, ...(plan ? { amount: String(Number(plan.amount)) } : {}) }))
  }

  // Current plans, plus the selected member's own plan if it's been retired.
  const memberPlan = getMemberById(form.memberId)?.paymentPlan
  const planOptions =
    memberPlan && !activePlans.some((p) => p.id === memberPlan.id) ? [...activePlans, memberPlan] : activePlans

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = validate(form)
    setErrors(validationErrors)
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      const transaction = await recordPayment({
        ...form,
        amount: Number(form.amount),
        dueDate: form.status === 'Pending' ? form.dueDate || form.date : form.date,
      })
      navigate(`/payments/membership/${transaction.id}`)
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="max-w-xl">
      <PageHeader
        back={{ to: '/payments/membership', label: 'Back to membership payments' }}
        title="Record payment"
        description="Log a one-time or recurring-installment payment."
      />

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-5">
          <FormField label="Member" htmlFor="memberId" required error={errors.memberId}>
            <MemberSelect id="memberId" value={form.memberId} onChange={handleMemberChange} error={errors.memberId} />
          </FormField>

          <FormField label="Payment plan" htmlFor="paymentPlanId" required error={errors.paymentPlanId}>
            <Select
              id="paymentPlanId"
              value={form.paymentPlanId}
              onChange={handlePlanChange}
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

          <div className="grid grid-cols-2 gap-5">
            <FormField label="Amount (LKR)" htmlFor="amount" required error={errors.amount}>
              <TextInput
                id="amount"
                type="number"
                min="0"
                step="0.01"
                value={form.amount}
                onChange={update('amount')}
                error={errors.amount}
                placeholder="5000.00"
              />
            </FormField>
            <FormField label="Payment method" htmlFor="method" required error={errors.method}>
              <Select id="method" value={form.method} onChange={update('method')} error={errors.method}>
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="Bank Transfer">Bank transfer</option>
                <option value="Online">Online</option>
              </Select>
            </FormField>
          </div>

          <div className="grid grid-cols-2 gap-5">
            <FormField label="Payment type" htmlFor="type">
              <Select id="type" value={form.type} onChange={update('type')}>
                <option value="OneTime">One-time</option>
                <option value="Recurring">Recurring installment</option>
              </Select>
            </FormField>
            <FormField label="Status" htmlFor="paymentStatus">
              <Select id="paymentStatus" value={form.status} onChange={update('status')}>
                <option value="Paid">Paid</option>
                <option value="Pending">Pending</option>
              </Select>
            </FormField>
          </div>

          <div className="grid grid-cols-2 gap-5">
            <FormField label="Date" htmlFor="date">
              <TextInput id="date" type="date" value={form.date} onChange={update('date')} />
            </FormField>
            {form.status === 'Pending' && (
              <FormField label="Due date" htmlFor="dueDate">
                <TextInput id="dueDate" type="date" value={form.dueDate} onChange={update('dueDate')} />
              </FormField>
            )}
          </div>

          <FormField label="Notes / reference" htmlFor="notes">
            <TextArea id="notes" rows={2} value={form.notes} onChange={update('notes')} placeholder="Optional" />
          </FormField>

          <div className="flex justify-end gap-2 pt-2 border-t border-[color:var(--color-line)]">
            <Button type="button" variant="secondary" onClick={() => navigate(-1)} disabled={saving}>
              Cancel
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save payment'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}
