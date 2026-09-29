import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { usePayments } from '../../../context/PaymentsContext'
import { useMembers } from '../../../context/MembersContext'
import Card from '../../../components/shared/Card'
import Button from '../../../components/shared/Button'
import StatusBadge from '../../../components/shared/StatusBadge'
import ReceiptView from '../../../components/payments/ReceiptView'
import { formatCurrency, formatDate, formatPlan } from '../../../utils/format'
import { paymentMember } from '../../../utils/paymentMember'

export default function MembershipDetailPage() {
  const { transactionId } = useParams()
  const navigate = useNavigate()
  const { getTransactionById, markTransactionPaid } = usePayments()
  const { getMemberById } = useMembers()

  const [receiptOpen, setReceiptOpen] = useState(false)
  const [marking, setMarking] = useState(false)

  const transaction = getTransactionById(transactionId)

  if (!transaction) {
    return (
      <Card className="p-8 text-center text-sm text-[color:var(--color-ink-soft)]">
        Membership payment not found.{' '}
        <Link to="/payments/membership" className="text-[color:var(--color-brand)] underline">
          Back to membership payments
        </Link>
      </Card>
    )
  }

  const member = paymentMember(transaction, getMemberById)
  const canMarkPaid = transaction.status === 'Pending'

  const handleMarkPaid = async () => {
    setMarking(true)
    try {
      await markTransactionPaid(transaction.id)
    } finally {
      setMarking(false)
    }
  }

  return (
    <div className="max-w-2xl">
      <button
        onClick={() => navigate('/payments/membership')}
        className="text-sm text-[color:var(--color-ink-soft)] hover:text-[color:var(--color-ink)] mb-4"
      >
        ← Back to membership payments
      </button>

      <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
          <div className="flex items-center gap-2">
            <span className="font-display text-2xl font-semibold tabular">
              {formatCurrency(transaction.amount)}
            </span>
            <StatusBadge status={transaction.status} />
          </div>
          <p className="mt-1 text-sm text-[color:var(--color-ink-soft)]">
            {transaction.invoiceNumber} ·{' '}
            {member && !member.removed ? (
              <Link to={`/members/${member.id}`} className="text-[color:var(--color-brand)] hover:underline">
                {member.fullName}
              </Link>
            ) : member ? (
              `${member.fullName} (deleted)`
            ) : (
              'Unknown member'
            )}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="secondary" onClick={() => setReceiptOpen(true)}>
            View receipt
          </Button>
          {canMarkPaid && (
            <Button onClick={handleMarkPaid} disabled={marking}>
              {marking ? 'Updating…' : 'Mark as paid'}
            </Button>
          )}
        </div>
      </div>

      <Card className="p-6">
        <dl className="grid grid-cols-2 gap-x-6 gap-y-5 text-sm">
          <Field label="Date" value={formatDate(transaction.date)} />
          <Field label="Due date" value={formatDate(transaction.dueDate)} />
          <Field label="Method" value={transaction.method} />
          <Field label="Type" value={transaction.type === 'OneTime' ? 'One-time' : 'Recurring'} />
          <Field label="Payment plan" value={transaction.paymentPlan ? formatPlan(transaction.paymentPlan) : '—'} />
          <Field label="Notes" value={transaction.notes || '—'} full />
        </dl>
      </Card>

      {member?.removed && (
        <Card className="p-6 mt-4">
          <h2 className="text-sm font-semibold text-[color:var(--color-ink)] mb-1">Member</h2>
          <p className="text-xs text-[color:var(--color-ink-faint)] mb-4">
            {member.removed === 'archived'
              ? 'This member was deleted and is no longer in the member list.'
              : 'This member was permanently removed; these details were saved with the payment.'}
          </p>
          <dl className="grid grid-cols-2 gap-x-6 gap-y-5 text-sm">
            <Field label="Name" value={member.fullName} />
            <Field label="Member ID number" value={member.memberIdNumber || '—'} />
            <Field label="Phone" value={member.phone || '—'} />
            {member.removed === 'archived' && (
              <>
                <Field label="Email" value={member.email || '—'} />
                <Field label="NIC" value={member.nic || '—'} />
                <Field label="Joined" value={member.joinDate ? formatDate(member.joinDate) : '—'} />
                <Field label="Deleted on" value={formatDate(member.deletedAt)} />
              </>
            )}
          </dl>
        </Card>
      )}

      <ReceiptView open={receiptOpen} onClose={() => setReceiptOpen(false)} transaction={transaction} member={member} />
    </div>
  )
}

function Field({ label, value, full }) {
  return (
    <div className={full ? 'col-span-2' : ''}>
      <dt className="text-xs text-[color:var(--color-ink-faint)] mb-1">{label}</dt>
      <dd className="text-[color:var(--color-ink)]">{value}</dd>
    </div>
  )
}
