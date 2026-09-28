import { useNavigate } from 'react-router-dom'
import { useMembers } from '../context/MembersContext'
import { usePayments } from '../context/PaymentsContext'
import Card from '../components/shared/Card'
import Button from '../components/shared/Button'
import { formatCurrency } from '../utils/format'

export default function DashboardPage() {
  const navigate = useNavigate()
  const { members } = useMembers()
  const { summary } = usePayments()

  const activeMembers = members.filter((m) => m.status === 'Active').length

  return (
    <div>
      <div className="mb-8">
        <h1 className="font-display text-2xl font-semibold text-[color:var(--color-ink)]">
          Good to see you.
        </h1>
        <p className="mt-1 text-sm text-[color:var(--color-ink-soft)]">
          Here's where things stand across members and payments today.
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Card className="p-6">
          <p className="text-xs text-[color:var(--color-ink-faint)] mb-1">Active members</p>
          <p className="font-display text-3xl font-semibold tabular mb-4">{activeMembers}</p>
          <p className="text-sm text-[color:var(--color-ink-soft)] mb-4">
            {members.length} total member profile{members.length === 1 ? '' : 's'} on file.
          </p>
          <Button variant="secondary" onClick={() => navigate('/members/all')}>
            Go to members
          </Button>
        </Card>

        <Card className="p-6">
          <p className="text-xs text-[color:var(--color-ink-faint)] mb-1">Revenue this month</p>
          <p className="font-display text-3xl font-semibold tabular mb-4">
            {summary ? formatCurrency(summary.revenueThisMonth) : '—'}
          </p>
          <p className="text-sm text-[color:var(--color-ink-soft)] mb-4">
            {summary ? `${summary.pendingCount} payment${summary.pendingCount === 1 ? '' : 's'} pending.` : ''}
          </p>
          <Button variant="secondary" onClick={() => navigate('/payments/summary')}>
            Go to payments
          </Button>
        </Card>
      </div>
    </div>
  )
}
