import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import * as guestService from '../../services/guestService'
import { formatDate } from '../../utils/format'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import Avatar from '../../components/shared/Avatar'
import StatusBadge from '../../components/shared/StatusBadge'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import MakeMemberDialog from './MakeMemberDialog'

// Read-only view of one guest registration, with Make member / Delete.
export default function GuestDetailPage() {
  const { guestId } = useParams()
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const { showToast } = useToast()
  const canEdit = hasPermission('members.edit')

  const [guest, setGuest] = useState(null)
  const [status, setStatus] = useState('loading') // loading | ready | missing
  const [promoteOpen, setPromoteOpen] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    setStatus('loading')
    guestService
      .getGuest(guestId)
      .then((data) => {
        setGuest(data)
        setStatus('ready')
      })
      .catch(() => setStatus('missing'))
  }, [guestId])

  if (status === 'loading') {
    return <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading guest…</div>
  }

  if (status === 'missing') {
    return (
      <Card className="p-8 text-center text-sm text-[color:var(--color-ink-soft)]">
        Guest not found — they may already be a member.{' '}
        <Link to="/members/guests" className="text-[color:var(--color-brand)] underline">
          Back to guests
        </Link>
      </Card>
    )
  }

  const handleDelete = async () => {
    setDeleting(true)
    try {
      await guestService.deleteGuest(guest.id)
      showToast(`${guest.fullName} deleted.`)
      navigate('/members/guests')
    } catch (err) {
      showToast(err.message || 'Could not delete the guest.', { tone: 'error' })
      setDeleting(false)
    }
  }

  return (
    <div>
      <Link
        to="/members/guests"
        className="inline-block text-sm text-[color:var(--color-ink-soft)] hover:text-[color:var(--color-ink)] mb-4"
      >
        ← Back to guests
      </Link>

      <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div className="flex items-center gap-4">
          <Avatar name={guest.fullName} size="lg" />
          <div>
            <h1 className="font-display text-2xl font-semibold text-[color:var(--color-ink)]">{guest.fullName}</h1>
            <div className="mt-1 flex items-center gap-2 text-sm text-[color:var(--color-ink-soft)]">
              <StatusBadge status="Guest" />
              <span>·</span>
              <span>Registered {formatDate(guest.createdAt)}</span>
            </div>
          </div>
        </div>
        {canEdit && (
          <div className="flex items-center gap-2">
            <Button variant="danger" onClick={() => setConfirmOpen(true)}>
              Delete
            </Button>
            <Button onClick={() => setPromoteOpen(true)}>Make member</Button>
          </div>
        )}
      </div>

      <Card className="p-6 max-w-2xl">
        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
          <Field label="Full name" value={guest.fullName} />
          <Field label="Username" value={guest.username && `@${guest.username}`} />
          <Field label="Phone" value={guest.phone} />
          <Field label="WhatsApp number" value={guest.whatsappNumber} />
          <Field label="Date of birth" value={guest.dob ? formatDate(guest.dob) : null} />
          <Field label="Registered" value={formatDate(guest.createdAt)} />
        </dl>
      </Card>

      <MakeMemberDialog
        guest={promoteOpen ? guest : null}
        onClose={() => setPromoteOpen(false)}
        onPromoted={(member) => navigate(`/members/${member.id}`)}
      />

      <ConfirmDialog
        open={confirmOpen}
        onClose={() => setConfirmOpen(false)}
        onConfirm={handleDelete}
        loading={deleting}
        title="Delete guest"
        description={`Permanently delete ${guest.fullName}'s registration? They'll need to sign up again.`}
        confirmLabel="Delete"
      />
    </div>
  )
}

function Field({ label, value }) {
  const empty = value === null || value === undefined || value === ''
  return (
    <div>
      <dt className="text-xs text-[color:var(--color-ink-faint)] mb-1">{label}</dt>
      <dd className="text-sm text-[color:var(--color-ink)]">{empty ? '—' : value}</dd>
    </div>
  )
}
