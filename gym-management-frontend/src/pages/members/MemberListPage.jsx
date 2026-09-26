import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMembers } from '../../context/MembersContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import SearchInput from '../../components/shared/SearchInput'
import { Select } from '../../components/shared/FormField'
import StatusBadge from '../../components/shared/StatusBadge'
import Avatar from '../../components/shared/Avatar'
import Pagination from '../../components/shared/Pagination'
import EmptyState from '../../components/shared/EmptyState'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'

const PAGE_SIZE = 6

export default function MemberListPage() {
  const { members, status } = useMembers()
  const navigate = useNavigate()

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
    </div>
  )
}

function PlusIcon() {
  return (
    <svg viewBox="0 0 20 20" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M10 4v12M4 10h12" strokeLinecap="round" />
    </svg>
  )
}
