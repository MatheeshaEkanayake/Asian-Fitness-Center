import { useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { usePaymentPlans } from '../../context/PaymentPlansContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import FormField, { TextInput } from '../../components/shared/FormField'

const emptyForm = { type: 'Monthly', months: '1', amount: '' }

const TYPES = [
  { value: 'Daily', label: 'Daily' },
  { value: 'Monthly', label: 'Monthly' },
]

function validate(form) {
  const errors = {}
  if (form.type === 'Monthly') {
    const months = Number(form.months)
    if (!form.months || !Number.isInteger(months) || months < 1) {
      errors.months = 'Enter a whole number of months (1 or more).'
    }
  }
  if (!form.amount || Number(form.amount) <= 0) errors.amount = 'Enter an amount greater than 0.'
  return errors
}

// Stored plans have numeric months/amount (or null months for Daily); the
// form works with strings, so convert both ways.
const toFormShape = (plan) => ({
  type: plan.type,
  months: plan.months != null ? String(plan.months) : '1',
  amount: String(Number(plan.amount)),
})

export default function PaymentPlanFormPage() {
  const { planId } = useParams()
  const isEdit = Boolean(planId)
  const navigate = useNavigate()
  const { status, addPlan, editPlan, getPlanById } = usePaymentPlans()

  const existing = isEdit ? getPlanById(planId) : null
  const [form, setForm] = useState(() => (existing ? toFormShape(existing) : emptyForm))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  // Opening the edit URL directly renders before plans have loaded; fill the
  // form once the plan arrives.
  const [loadedId, setLoadedId] = useState(existing?.id ?? null)
  if (existing && loadedId !== existing.id) {
    setLoadedId(existing.id)
    setForm(toFormShape(existing))
  }

  const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }))

  const baseline = useMemo(() => (existing ? toFormShape(existing) : emptyForm), [existing])
  const isDirty = JSON.stringify(form) !== JSON.stringify(baseline)

  const handleCancel = () => {
    if (isDirty && !window.confirm('Discard unsaved changes?')) return
    navigate('/setup/payment-plans')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = validate(form)
    setErrors(validationErrors)
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      const input = {
        type: form.type,
        months: form.type === 'Monthly' ? Number(form.months) : null,
        amount: Number(form.amount),
      }
      if (isEdit) {
        await editPlan(existing.id, input)
      } else {
        await addPlan(input)
      }
      navigate('/setup/payment-plans')
    } finally {
      setSaving(false)
    }
  }

  if (isEdit && !existing) {
    return (
      <Card className="p-8 text-center text-sm text-[color:var(--color-ink-soft)]">
        {status === 'loading' ? 'Loading plan…' : 'Plan not found.'}
      </Card>
    )
  }

  return (
    <div className="max-w-lg">
      <PageHeader
        back={{ to: '/setup/payment-plans', label: 'Back to payment plans' }}
        title={isEdit ? `Edit plan: ${existing.name}` : 'New plan'}
        description={
          isEdit
            ? 'Members on this plan move to the new price and length. Past payments keep the amount actually paid, but will show the updated plan name.'
            : 'Several plans can be offered at once, e.g. Daily, 1 month and 3 months.'
        }
      />
      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-5">
          <FormField label="Plan type" required>
            <div
              role="radiogroup"
              aria-label="Plan type"
              className="inline-flex w-full rounded-md border border-[color:var(--color-line-strong)] p-0.5 bg-[color:var(--color-paper)]"
            >
              {TYPES.map((t) => {
                const selected = form.type === t.value
                return (
                  <button
                    key={t.value}
                    type="button"
                    role="radio"
                    aria-checked={selected}
                    onClick={() => setForm((f) => ({ ...f, type: t.value }))}
                    className={`flex-1 rounded px-3 py-1.5 text-sm font-medium transition-colors ${
                      selected
                        ? 'bg-[color:var(--color-surface)] text-[color:var(--color-ink)] shadow-sm'
                        : 'text-[color:var(--color-ink-soft)] hover:text-[color:var(--color-ink)]'
                    }`}
                  >
                    {t.label}
                  </button>
                )
              })}
            </div>
          </FormField>

          {form.type === 'Monthly' && (
            <FormField label="Number of months" htmlFor="months" required error={errors.months}>
              <TextInput
                id="months"
                type="number"
                min="1"
                step="1"
                value={form.months}
                onChange={update('months')}
                error={errors.months}
              />
            </FormField>
          )}

          <FormField
            label="Amount (LKR)"
            htmlFor="amount"
            required
            error={errors.amount}
            hint={form.type === 'Daily' ? 'Price for a single day.' : 'Total price for the whole plan.'}
          >
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

          <div className="flex justify-end gap-2 pt-2 border-t border-[color:var(--color-line)]">
            <Button type="button" variant="secondary" onClick={handleCancel} disabled={saving}>
              Cancel
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save plan'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}
