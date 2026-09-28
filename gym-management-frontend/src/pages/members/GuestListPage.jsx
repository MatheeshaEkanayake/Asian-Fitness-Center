import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import * as guestService from '../../services/guestService'
import { formatDate } from '../../utils/format'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import SearchInput from '../../components/shared/SearchInput'
import Avatar from '../../components/shared/Avatar'
import Pagination from '../../components/shared/Pagination'
import EmptyState from '../../components/shared/EmptyState'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'
import MakeMemberDialog from './MakeMemberDialog'

const PAGE_SIZE = 10

// Members > Guests — everyone who registered on /signup and hasn't been made
// a member yet (staff registrations granted a role are left out). Backend:
// GuestController.
export default function GuestListPage() {
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const { showToast } = useToast()
  const canEdit = hasPermission('members.edit')

  const [guests, setGuests] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)

  const [promoting, setPromoting] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [deleteBusy, setDeleteBusy] = useState(false)

  const load = useCallback(async () => {
    try {
      setGuests(await guestService.listGuests())
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    if (!q) return guests
    return guests.filter((g) =>
      [g.fullName, g.username, g.phone, g.whatsappNumber].some((v) => v?.toLowerCase().includes(q))
    )
  }, [guests, query])

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const paged = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  const removeFromList = (id) => setGuests((current) => current.filter((g) => g.id !== id))

  const handleDelete = async () => {
    setDeleteBusy(true)
    try {
      await guestService.deleteGuest(deleting.id)
      removeFromList(deleting.id)
      showToast(`${deleting.fullName} deleted.`)
      setDeleting(null)
    } catch (err) {
      showToast(err.message || 'Could not delete the guest.', { tone: 'error' })
    } finally {
      setDeleteBusy(false)
    }
  }

  // Row buttons sit inside a clickable row — keep their clicks to themselves.
  const act = (fn) => (e) => {
    e.stopPropagation()
    fn()
  }

  return (
    <div>
      <PageHeader
        title="Guests"
        description="People who registered on the sign-up page. Make them a member once they've chosen a plan."
      />

      <div className="mb-4">
        <SearchInput
          value={query}
          onChange={(value) => {
            setQuery(value)
            setPage(1)
          }}
          placeholder="Search by name, username or phone…"
          className="w-full sm:w-72"
        />
      </div>

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading guests…</div>
        ) : status === 'error' ? (
          <EmptyState
            title="Couldn't load guests"
            description="Check your connection and try again."
            action={<Button onClick={load}>Retry</Button>}
          />
        ) : filtered.length === 0 ? (
          <EmptyState
            title={guests.length === 0 ? 'No guests' : 'No guests found'}
            description={
              guests.length === 0
                ? 'New registrations from the sign-up page appear here.'
                : 'Try a different search term.'
            }
          />
        ) : (
          <>
            <Table>
              <THead>
                <Th>Guest</Th>
                <Th>Phone</Th>
                <Th>WhatsApp</Th>
                <Th>Registered</Th>
                {canEdit && <Th className="text-right">Actions</Th>}
              </THead>
              <TBody>
                {paged.map((guest) => (
                  <Tr key={guest.id} onClick={() => navigate(`/members/guests/${guest.id}`)}>
                    <Td>
                      <div className="flex items-center gap-3">
                        <Avatar name={guest.fullName} size="sm" />
                        <div>
                          <div className="font-medium">{guest.fullName}</div>
                          <div className="text-xs text-[color:var(--color-ink-faint)]">@{guest.username}</div>
                        </div>
                      </div>
                    </Td>
                    <Td className="text-[color:var(--color-ink-soft)]">{guest.phone || '—'}</Td>
                    <Td className="text-[color:var(--color-ink-soft)]">{guest.whatsappNumber || '—'}</Td>
                    <Td className="text-[color:var(--color-ink-soft)]">{formatDate(guest.createdAt)}</Td>
                    {canEdit && (
                      <Td>
                        <div className="flex justify-end gap-2">
                          <Button onClick={act(() => setPromoting(guest))}>Make member</Button>
                          <Button variant="danger" onClick={act(() => setDeleting(guest))}>
                            Delete
                          </Button>
                        </div>
                      </Td>
                    )}
                  </Tr>
                ))}
              </TBody>
            </Table>
            <Pagination
              page={page}
              pageCount={pageCount}
              onPageChange={setPage}
              totalItems={filtered.length}
              pageSize={PAGE_SIZE}
            />
          </>
        )}
      </Card>

      <MakeMemberDialog
        guest={promoting}
        onClose={() => setPromoting(null)}
        onPromoted={(member) => {
          removeFromList(member.id)
          setPromoting(null)
        }}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={handleDelete}
        loading={deleteBusy}
        title="Delete guest"
        description={`Permanently delete ${deleting?.fullName}'s registration? They'll need to sign up again.`}
        confirmLabel="Delete"
      />
    </div>
  )
}
