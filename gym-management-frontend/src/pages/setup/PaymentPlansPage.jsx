import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { usePaymentPlans } from '../../context/PaymentPlansContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import EmptyState from '../../components/shared/EmptyState'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'
import { formatCurrency, formatDate } from '../../utils/format'

export default function PaymentPlansPage() {
  const { plans, activePlans, status, setPlanActive } = usePaymentPlans()
  const navigate = useNavigate()

  const [pendingRetire, setPendingRetire] = useState(null)
  const [retiring, setRetiring] = useState(false)
  const [reactivatingId, setReactivatingId] = useState(null)

  const retiredPlans = plans.filter((p) => !p.isActive)

  const handleRetire = async () => {
    setRetiring(true)
    try {
      await setPlanActive(pendingRetire.id, false)
      setPendingRetire(null)
    } finally {
      setRetiring(false)
    }
  }

  const handleReactivate = async (plan) => {
    setReactivatingId(plan.id)
    try {
      await setPlanActive(plan.id, true)
    } finally {
      setReactivatingId(null)
    }
  }

  return (
    <div>
      <PageHeader
        back={{ to: '/setup', label: 'Back to setup' }}
        title="Payment Plans"
        description="Membership plans offered at the gym. Members are signed up to one of these on the member form."
        actions={<Button onClick={() => navigate('/setup/payment-plans/new')}>New plan</Button>}
      />

      <h2 className="font-display text-base font-semibold text-[color:var(--color-ink)] mb-3">Current plans</h2>
      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading plans…</div>
        ) : activePlans.length === 0 ? (
          <EmptyState
            title="No current plans"
            description="Add a plan so new members can be signed up to it."
          />
        ) : (
          <PlansTable
            plans={activePlans}
            renderAction={(plan) => (
              <div className="flex justify-end gap-2">
                <Button variant="ghost" onClick={() => navigate(`/setup/payment-plans/${plan.id}/edit`)}>
                  Edit
                </Button>
                <Button variant="danger" onClick={() => setPendingRetire(plan)}>
                  Retire
                </Button>
              </div>
            )}
          />
        )}
      </Card>

      {retiredPlans.length > 0 && (
        <>
          <h2 className="font-display text-base font-semibold text-[color:var(--color-ink)] mt-8 mb-3">
            Retired plans
          </h2>
          <Card>
            <PlansTable
              plans={retiredPlans}
              muted
              renderAction={(plan) => (
                <div className="flex justify-end gap-2">
                  <Button variant="ghost" onClick={() => navigate(`/setup/payment-plans/${plan.id}/edit`)}>
                    Edit
                  </Button>
                  <Button
                    variant="ghost"
                    onClick={() => handleReactivate(plan)}
                    disabled={reactivatingId === plan.id}
                  >
                    {reactivatingId === plan.id ? 'Reactivating…' : 'Reactivate'}
                  </Button>
                </div>
              )}
            />
          </Card>
        </>
      )}

      <ConfirmDialog
        open={Boolean(pendingRetire)}
        onClose={() => setPendingRetire(null)}
        onConfirm={handleRetire}
        title="Retire plan?"
        description={`"${pendingRetire?.name}" will no longer be offered to new members. Members already on it keep it, and it can be reactivated later.`}
        confirmLabel="Retire"
        loading={retiring}
      />
    </div>
  )
}

function PlansTable({ plans, muted, renderAction }) {
  return (
    <Table>
      <THead>
        <Th>Plan</Th>
        <Th>Type</Th>
        <Th>Amount</Th>
        <Th>Created</Th>
        <Th></Th>
      </THead>
      <TBody>
        {plans.map((plan) => (
          <Tr key={plan.id}>
            <Td className={muted ? 'text-[color:var(--color-ink-soft)]' : 'font-medium'}>{plan.name}</Td>
            <Td className="text-[color:var(--color-ink-soft)]">{plan.type}</Td>
            <Td className="tabular">{formatCurrency(plan.amount)}</Td>
            <Td className="tabular text-[color:var(--color-ink-soft)]">{formatDate(plan.createdAt)}</Td>
            <Td className="text-right">{renderAction(plan)}</Td>
          </Tr>
        ))}
      </TBody>
    </Table>
  )
}
