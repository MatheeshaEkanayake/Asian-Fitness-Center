import { useNavigate } from 'react-router-dom'
import { usePayments } from '../../context/PaymentsContext'
import { useMembers } from '../../context/MembersContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import StatusBadge from '../../components/shared/StatusBadge'
import EmptyState from '../../components/shared/EmptyState'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'
import { formatCurrency, formatDate } from '../../utils/format'
import { paymentMember } from '../../utils/paymentMember'

export default function PaymentsDashboardPage() {
  const { summary, transactions, status } = usePayments()
  const { getMemberById } = useMembers()
  const navigate = useNavigate()

  const recent = transactions.slice(0, 6)

  const stats = summary
    ? [
        { label: 'Revenue this month', value: formatCurrency(summary.revenueThisMonth), accent: 'brand' },
        { label: 'Pending payments', value: summary.pendingCount, accent: 'amber' },
        { label: 'Overdue', value: summary.overdueCount, accent: 'danger' },
        { label: 'Failed payments', value: summary.failedCount, accent: 'slate' },
      ]
    : []

  return (
    <div>
      <PageHeader
        title="Payments"
        description="A snapshot of revenue, pending activity."
        actions={
          <Button onClick={() => navigate('/payments/membership/new')}>Record payment</Button>
        }
      />

      {status === 'loading' ? (
        <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">
          Loading payment data…
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
            {stats.map((stat) => (
              <StatCard key={stat.label} {...stat} />
            ))}
          </div>

          <Card>
            <div className="flex items-center justify-between px-4 pt-4">
              <h2 className="font-display text-base font-semibold">Recent membership payments</h2>
              <button
                onClick={() => navigate('/payments/membership')}
                className="text-sm text-[color:var(--color-brand)] hover:underline"
              >
                View all →
              </button>
            </div>
            {recent.length === 0 ? (
              <EmptyState title="No membership payments yet" description="Recorded payments will appear here." />
            ) : (
              <Table>
                <THead>
                  <Th>Member</Th>
                  <Th>Date</Th>
                  <Th>Amount</Th>
                  <Th>Type</Th>
                  <Th>Status</Th>
                </THead>
                <TBody>
                  {recent.map((t) => {
                    const member = paymentMember(t, getMemberById)
                    return (
                      <Tr key={t.id} onClick={() => navigate(`/payments/membership/${t.id}`)}>
                        <Td>{member ? member.fullName : 'Unknown member'}</Td>
                        <Td className="tabular text-[color:var(--color-ink-soft)]">{formatDate(t.date)}</Td>
                        <Td className="tabular">{formatCurrency(t.amount)}</Td>
                        <Td className="text-[color:var(--color-ink-soft)]">{t.type === 'OneTime' ? 'One-time' : 'Recurring'}</Td>
                        <Td>
                          <StatusBadge status={t.status} />
                        </Td>
                      </Tr>
                    )
                  })}
                </TBody>
              </Table>
            )}
          </Card>
        </>
      )}
    </div>
  )
}

const ACCENTS = {
  brand: 'var(--color-brand)',
  amber: 'var(--color-amber)',
  danger: 'var(--color-danger)',
  slate: 'var(--color-slate)',
}

function StatCard({ label, value, accent }) {
  return (
    <Card className="p-4 relative overflow-hidden">
      <span
        className="absolute left-0 top-0 h-full w-1"
        style={{ backgroundColor: ACCENTS[accent] }}
        aria-hidden="true"
      />
      <div className="pl-2">
        <div className="font-display text-2xl font-semibold tabular text-[color:var(--color-ink)]">
          {value}
        </div>
        <div className="mt-1 text-xs text-[color:var(--color-ink-soft)]">{label}</div>
      </div>
    </Card>
  )
}
