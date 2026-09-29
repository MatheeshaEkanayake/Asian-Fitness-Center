import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMembers } from '../../context/MembersContext'
import { useAuth } from '../../context/AuthContext'
import { useToast } from '../../context/ToastContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import SearchInput from '../../components/shared/SearchInput'
import { Select } from '../../components/shared/FormField'
import StatusBadge from '../../components/shared/StatusBadge'
import Avatar from '../../components/shared/Avatar'
import Pagination from '../../components/shared/Pagination'
import EmptyState from '../../components/shared/EmptyState'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'

const PAGE_SIZE = 6

export default function MemberListPage() {
  const { members, status, setGateAccess, archiveMember } = useMembers()
  const { hasPermission } = useAuth()
  const { showToast } = useToast()
  const navigate = useNavigate()

  const canEdit = hasPermission('members.edit')
  const canToggleGate = hasPermission('members.gate_access')
  const canDelete = hasPermission('members.delete')
  const showActions = canEdit || canToggleGate || canDelete

  const [gateBusyId, setGateBusyId] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [deleteBusy, setDeleteBusy] = useState(false)

  const [query, setQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('All')
  const [sortBy, setSortBy] = useState('name')
  const [page, setPage] = useState(1)

  const filtered = useMemo(() => {
    let list = members
    if (statusFilter !== 'All') {
      list = list.filter((m) => m.status === statusFilter)
    }
    if (query.trim()) {
      const q = query.trim().toLowerCase()
      list = list.filter(
        (m) =>
          m.fullName.toLowerCase().includes(q) ||
          m.email?.toLowerCase().includes(q) ||
          m.phone?.toLowerCase().includes(q)
      )
    }
    list = [...list].sort((a, b) => {
      if (sortBy === 'name') return a.fullName.localeCompare(b.fullName)
      return new Date(b.joinDate) - new Date(a.joinDate)
    })
    return list
  }, [members, query, statusFilter, sortBy])

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const paged = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  const toggleGate = async (member) => {
    setGateBusyId(member.id)
    try {
      await setGateAccess(member.id, member.status !== 'Active')
    } catch (err) {
      showToast(err.message || 'Could not change gate access.', { tone: 'error' })
    } finally {
      setGateBusyId(null)
    }
  }

  const handleDelete = async () => {
    setDeleteBusy(true)
    try {
      await archiveMember(deleting)
      setDeleting(null)
    } catch (err) {
      showToast(err.message || 'Could not delete the member.', { tone: 'error' })
    } finally {
      setDeleteBusy(false)
    }
  }

  // Row controls sit inside a clickable row — keep their clicks to themselves.
  const act = (fn) => (e) => {
    e.stopPropagation()
    fn()
  }

  const resetToFirstPage = (fn) => (value) => {
    fn(value)
    setPage(1)
  }

  return (
    <div>
      <PageHeader
        title="Members"
        description="View and manage every member's profile and status."
        actions={
          <Button onClick={() => navigate('/members/new')}>
            <PlusIcon /> Add member
          </Button>
        }
      />
        <div className="flex flex-col gap-3 mb-4">
          <SearchInput
            value={query}
            onChange={resetToFirstPage(setQuery)}
            placeholder="Search by name, email or phone…"
            className="w-full sm:w-72"
        />
        <div className="flex flex-wrap items-center gap-3">
          <Select
            value={statusFilter}
            onChange={(e) => resetToFirstPage(setStatusFilter)(e.target.value)}
            className="w-auto!"
            aria-label="Filter by status"
          >
            <option value="All">All statuses</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Suspended">Suspended</option>
          </Select>
          <Select
            value={sortBy}
            onChange={(e) => setSortBy(e.target.value)}
            className="w-auto!"
            aria-label="Sort members"
          >
            <option value="name">Sort by name</option>
            <option value="joinDate">Sort by join date</option>
          </Select>
        </div>
      </div>

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">
            Loading members…
          </div>
        ) : filtered.length === 0 ? (
          <EmptyState
            title="No members found"
            description={
              members.length === 0
                ? 'Add your first member to get started.'
                : 'Try a different search term or filter.'
            }
            action={
              members.length === 0 && (
                <Button onClick={() => navigate('/members/new')}>Add member</Button>
              )
            }
          />
        ) : (
          <>
            <Table>
              <THead>
                <Th>Member</Th>
                <Th>Payment plan</Th>
                <Th>Payment status</Th>
                <Th>Today</Th>
                {showActions && <Th className="text-right">Actions</Th>}
              </THead>
              <TBody>
                {paged.map((member) => (
                  <Tr key={member.id} onClick={() => navigate(`/members/${member.id}`)}>
                    <Td>
                      <div className="flex items-center gap-3">
                        <Avatar name={member.fullName} size="sm" />
                        <div>
                          <div className="font-medium">{member.fullName}</div>
                          <div className="text-xs text-[color:var(--color-ink-faint)]">
                            {member.email}
                          </div>
                        </div>
                      </div>
                    </Td>
                    <Td className="text-[color:var(--color-ink-soft)]">
                      {member.paymentPlan?.name || '—'}
                    </Td>
                    <Td>
                      <StatusBadge status={member.paymentStatus} />
                    </Td>
                    <Td>
                      <StatusBadge status={member.todayAttendanceStatus} />
                    </Td>
                    {showActions && (
                      <Td>
                        <div className="flex items-center justify-end gap-2">
                          {canToggleGate && (
                            <GateSwitch
                              on={member.status === 'Active'}
                              busy={gateBusyId === member.id}
                              onClick={act(() => toggleGate(member))}
                            />
                          )}
                          {canEdit && (
                            <Button variant="secondary" onClick={act(() => navigate(`/members/${member.id}/edit`))}>
                              Edit
                            </Button>
                          )}
                          {canDelete && (
                            <Button variant="danger" onClick={act(() => setDeleting(member))}>
                              Delete
                            </Button>
                          )}
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

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={handleDelete}
        loading={deleteBusy}
        title="Delete member"
        description={`Are you sure you want to delete ${deleting?.fullName}? They'll lose gate access and be removed from the member list. Their payment history is kept, and they're permanently deleted after 6 months.`}
        confirmLabel="Yes, delete"
      />
    </div>
  )
}

// Gate access on/off. Door access follows status: on = Active, off = Inactive.
function GateSwitch({ on, busy, onClick }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      aria-label="Gate access"
      title={on ? 'Gate access on — click to deactivate' : 'Gate access off — click to activate'}
      disabled={busy}
      onClick={onClick}
      className="inline-flex items-center gap-2 text-xs text-[color:var(--color-ink-soft)] disabled:opacity-50"
    >
      <span
        className={`relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors ${
          on ? 'bg-[color:var(--color-brand)]' : 'bg-[color:var(--color-line-strong)]'
        }`}
      >
        <span
          className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform ${
            on ? 'translate-x-4' : 'translate-x-0.5'
          }`}
        />
      </span>
      Gate
    </button>
  )
}

function PlusIcon() {
  return (
    <svg viewBox="0 0 20 20" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M10 4v12M4 10h12" strokeLinecap="round" />
    </svg>
  )
}
