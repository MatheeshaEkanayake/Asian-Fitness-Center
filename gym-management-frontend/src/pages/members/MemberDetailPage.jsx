import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMembers } from '../../context/MembersContext'
import { usePayments } from '../../context/PaymentsContext'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import StatusBadge from '../../components/shared/StatusBadge'
import Avatar from '../../components/shared/Avatar'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import EmptyState from '../../components/shared/EmptyState'
import EnrollOnDevice from '../../components/shared/EnrollOnDevice'
import * as memberService from '../../services/memberService'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'
import { formatCurrency, formatDate, formatDateTime, formatPlan } from '../../utils/format'
import { kgToLb, cmToFtIn, round } from '../../utils/units'

const TABS = ['Profile', 'Payment history']

export default function MemberDetailPage() {
  const { memberId } = useParams()
  const navigate = useNavigate()
  const { getMemberById, deactivateMember } = useMembers()
  const { getTransactionsForMember } = usePayments()

  const [tab, setTab] = useState('Profile')
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [deactivating, setDeactivating] = useState(false)

  const member = getMemberById(memberId)

  if (!member) {
    return (
      <Card className="p-8 text-center text-sm text-[color:var(--color-ink-soft)]">
        Member not found.{' '}
        <Link to="/members/all" className="text-[color:var(--color-brand)] underline">
          Back to members
        </Link>
      </Card>
    )
  }

  const transactions = getTransactionsForMember(member.id)

  const handleDeactivate = async () => {
    setDeactivating(true)
    try {
      await deactivateMember(member.id)
      setConfirmOpen(false)
    } finally {
      setDeactivating(false)
    }
  }

  return (
    <div>
      <button
        onClick={() => navigate('/members/all')}
        className="text-sm text-[color:var(--color-ink-soft)] hover:text-[color:var(--color-ink)] mb-4"
      >
        ← Back to members
      </button>

      <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div className="flex items-center gap-4">
          <Avatar name={member.fullName} size="lg" />
          <div>
            <h1 className="font-display text-2xl font-semibold text-[color:var(--color-ink)]">
              {member.fullName}
            </h1>
            <div className="mt-1 flex items-center gap-2 text-sm text-[color:var(--color-ink-soft)]">
              <StatusBadge status={member.status} />
              <span>·</span>
              <span>Member since {formatDate(member.joinDate)}</span>
            </div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="secondary" onClick={() => navigate(`/members/${member.id}/edit`)}>
            Edit
          </Button>
          {member.status !== 'Inactive' && (
            <Button variant="danger" onClick={() => setConfirmOpen(true)}>
              Deactivate
            </Button>
          )}
          <Button onClick={() => navigate(`/payments/membership/new?memberId=${member.id}`)}>
            Record payment
          </Button>
        </div>
      </div>

      <div className="flex gap-1 border-b border-[color:var(--color-line)] mb-5">
        {TABS.map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-3 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors ${
              tab === t
                ? 'border-[color:var(--color-brand)] text-[color:var(--color-ink)]'
                : 'border-transparent text-[color:var(--color-ink-soft)] hover:text-[color:var(--color-ink)]'
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      {tab === 'Profile' ? (
        <Card className="p-6 max-w-2xl space-y-6">
          <Section title="Membership">
            <Field label="Status" value={<StatusBadge status={member.status} />} />
            <Field label="Join date" value={member.joinDate ? formatDate(member.joinDate) : null} />
          </Section>

          <DoorAccessSection member={member} />

          <Section title="Personal">
            <Field label="Member ID number (door PIN)" value={member.memberIdNumber} />
            <Field label="NIC" value={member.nic} />
            <Field label="Date of birth" value={member.dob ? formatDate(member.dob) : null} />
            <Field label="Gender" value={member.gender} />
            <Field label="Occupation" value={member.occupation} />
          </Section>

          <Section title="Contact">
            <Field label="Email" value={member.email} />
            <Field label="Phone" value={member.phone} />
            <Field label="WhatsApp number" value={member.whatsappNumber} />
            <Field label="Address" value={member.address} full />
          </Section>

          <Section title="Body measurements">
            <Field label="Weight" value={formatWeight(member.weightKg)} />
            <Field label="Height" value={formatHeight(member.heightCm)} />
          </Section>

          <Section title="Emergency contact">
            <Field label="Name" value={member.emergencyContactName} />
            <Field label="Phone" value={member.emergencyContactPhone} />
          </Section>

          <Section title="Account">
            <Field label="Username" value={member.username} />
            <Field
              label="Payment plan"
              value={
                member.paymentPlan
                  ? `${formatPlan(member.paymentPlan)}${member.paymentPlan.isActive ? '' : ' (retired)'}`
                  : null
              }
            />
          </Section>

          <Section title="Notes">
            <Field label="Notes" value={member.notes} full />
          </Section>
        </Card>
      ) : (
        <Card>
          {transactions.length === 0 ? (
            <EmptyState
              title="No payments yet"
              description="Payments recorded for this member will show up here."
            />
          ) : (
            <>
              <Table>
                <THead>
                  <Th>Date</Th>
                  <Th>Invoice</Th>
                  <Th>Amount</Th>
                  <Th>Status</Th>
                </THead>
                <TBody>
                  {transactions.slice(0, 5).map((t) => (
                    <Tr key={t.id} onClick={() => navigate(`/payments/membership/${t.id}`)}>
                      <Td className="tabular text-[color:var(--color-ink-soft)]">{formatDate(t.date)}</Td>
                      <Td className="tabular">{t.invoiceNumber}</Td>
                      <Td className="tabular">{formatCurrency(t.amount)}</Td>
                      <Td>
                        <StatusBadge status={t.status} />
                      </Td>
                    </Tr>
                  ))}
                </TBody>
              </Table>
              <div className="px-4 py-3 border-t border-[color:var(--color-line)]">
                <Link
                  to={`/payments/membership?memberId=${member.id}`}
                  className="text-sm text-[color:var(--color-brand)] hover:underline"
                >
                  View all payment history →
                </Link>
              </div>
            </>
          )}
        </Card>
      )}

      <ConfirmDialog
        open={confirmOpen}
        onClose={() => setConfirmOpen(false)}
        onConfirm={handleDeactivate}
        title="Deactivate member?"
        description={`${member.fullName} will be marked inactive. Their profile and payment history are kept, and they can be reactivated later by editing their status.`}
        confirmLabel="Deactivate"
        loading={deactivating}
      />
    </div>
  )
}

// Device sync results (MemberDeviceSync on the backend).
const DEVICE_STATUS = {
  synced: 'On the door device',
  dry_run: 'Dry run — logged, not sent (VFT_MODE=log)',
  failed: 'Last sync failed',
  no_pin: 'Not on the device',
  no_access: 'Not on the device — no active paid access',
}

function DoorAccessSection({ member }) {
  const hasPin = /^\d{1,9}$/.test(member.memberIdNumber || '')
  const validity =
    member.accessValidUntil &&
    `${member.accessValidFrom ? formatDate(member.accessValidFrom) : '…'} – ${formatDate(member.accessValidUntil)}`
  const notes = [
    !hasPin && 'Not on the door device — add a Member ID number (digits only) to put them on it.',
    member.accessNote,
    member.deviceSyncStatus === 'failed' && member.deviceSyncError,
  ].filter(Boolean)

  return (
    <Section title="Door access">
      <Field label="Access valid" value={validity || 'No paid access'} />
      <Field
        label="Door device"
        value={
          member.deviceSyncStatus
            ? `${DEVICE_STATUS[member.deviceSyncStatus] || member.deviceSyncStatus}${
                member.deviceSyncedAt ? ` · ${formatDateTime(member.deviceSyncedAt)}` : ''
              }`
            : 'Not synced yet'
        }
      />
      {notes.map((note) => (
        <div
          key={note}
          className="sm:col-span-2 rounded-md bg-[color:var(--color-amber-soft)] px-3 py-2 text-sm text-[color:var(--color-amber)]"
        >
          {note}
        </div>
      ))}
      {hasPin && (
        <EnrollOnDevice
          enroll={(input) => memberService.enrollOnDevice(member.id, input)}
          disabledReason={
            member.devicePinSynced === member.memberIdNumber || member.deviceSyncStatus === 'dry_run'
              ? null
              : 'Enrollment opens once they are on the door device (after a paid membership is recorded).'
          }
        />
      )}
    </Section>
  )
}

function Section({ title, children }) {
  return (
    <section className="pt-6 first:pt-0 border-t first:border-t-0 border-[color:var(--color-line)]">
      <h2 className="font-display text-base font-semibold text-[color:var(--color-ink)] mb-4">{title}</h2>
      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">{children}</dl>
    </section>
  )
}

function Field({ label, value, full }) {
  const empty = value === null || value === undefined || value === ''
  return (
    <div className={full ? 'sm:col-span-2' : ''}>
      <dt className="text-xs text-[color:var(--color-ink-faint)] mb-1">{label}</dt>
      <dd className="text-sm text-[color:var(--color-ink)] whitespace-pre-line">{empty ? '—' : value}</dd>
    </div>
  )
}

// Weight/height are stored as kg/cm (see MemberFormPage.jsx); show the
// imperial equivalent alongside so staff don't have to convert.
function formatWeight(weightKg) {
  if (weightKg === null || weightKg === undefined || weightKg === '') return null
  const kg = Number(weightKg)
  return `${round(kg, 1)} kg (${round(kgToLb(kg), 1)} lb)`
}

function formatHeight(heightCm) {
  if (heightCm === null || heightCm === undefined || heightCm === '') return null
  const cm = Number(heightCm)
  const { ft, inch } = cmToFtIn(cm)
  return `${round(cm, 1)} cm (${ft} ft ${round(inch, 1)} in)`
}
