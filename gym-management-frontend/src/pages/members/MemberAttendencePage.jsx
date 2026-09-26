import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import SearchInput from '../../components/shared/SearchInput'
import { Select } from '../../components/shared/FormField'
import StatusBadge from '../../components/shared/StatusBadge'
import Avatar from '../../components/shared/Avatar'
import Pagination from '../../components/shared/Pagination'
import EmptyState from '../../components/shared/EmptyState'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'
import { formatDate } from '../../utils/format'
import { listAttendance } from '../../services/attendanceService'

const PAGE_SIZE = 6

export default function MemberAttendencePage() {
  const navigate = useNavigate()

  // NOTE: was `import { initialAttendance } from '../../data/mockData'` — now
  // fetched from the real API (see src/services/attendanceService.js).
  const [records, setRecords] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error

  const [query, setQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('All')
  const [sortBy, setSortBy] = useState('date')
  const [page, setPage] = useState(1)

  useEffect(() => {
    let cancelled = false
    setStatus('loading')
    listAttendance()
      .then((data) => {
        if (cancelled) return
        setRecords(data)
        setStatus('ready')
      })
      .catch(() => {
        if (!cancelled) setStatus('error')
      })
    return () => {
      cancelled = true
    }
  }, [])

  const filtered = useMemo(() => {
    let list = records
    if (statusFilter !== 'All') {
      list = list.filter((a) => a.status === statusFilter)
    }
    if (query.trim()) {
      const q = query.trim().toLowerCase()
      list = list.filter(
        (a) =>
          a.memberName.toLowerCase().includes(q) ||
          a.email?.toLowerCase().includes(q) ||
          a.date.toLowerCase().includes(q)
      )
    }
    list = [...list].sort((a, b) => {
      if (sortBy === 'name') return a.memberName.localeCompare(b.memberName)
      return new Date(b.date) - new Date(a.date)
    })
    return list
  }, [records, query, statusFilter, sortBy])

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const paged = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  const resetToFirstPage = (fn) => (value) => {
    fn(value)
    setPage(1)
  }

  return (
    <div>
      <PageHeader
        title="Attendance"
        description="View and monitor daily member attendance records."
      />

      <div className="flex flex-col gap-3 mb-4">
        <SearchInput
          value={query}
          onChange={resetToFirstPage(setQuery)}
          placeholder="Search by name, email or date…"
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
            <option value="Present">Present</option>
            <option value="Late">Late</option>
            <option value="Absent">Absent</option>
          </Select>
          <Select
            value={sortBy}
            onChange={(e) => setSortBy(e.target.value)}
            className="w-auto!"
            aria-label="Sort attendance"
          >
            <option value="date">Sort by date</option>
            <option value="name">Sort by name</option>
          </Select>
        </div>
      </div>

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading attendance…</div>
        ) : status === 'error' ? (
          <EmptyState title="Couldn't load attendance" description="Please try again later." />
        ) : filtered.length === 0 ? (
          <EmptyState
            title="No attendance records found"
            description="Try a different search term or filter."
          />
        ) : (
          <>
            <Table>
              <THead>
                <Th>Member</Th>
                <Th>Date</Th>
                <Th>Check In</Th>
                <Th>Check Out</Th>
                <Th>Status</Th>
              </THead>
              <TBody>
                {paged.map((record) => (
                  <Tr
                    key={record.id}
                    onClick={() => navigate(`/members/${record.memberId}`)}
                  >
                    <Td>
                      <div className="flex items-center gap-3">
                        <Avatar name={record.memberName} size="sm" />
                        <div>
                          <div className="font-medium">{record.memberName}</div>
                          <div className="text-xs text-[color:var(--color-ink-faint)]">
                            {record.email}
                          </div>
                        </div>
                      </div>
                    </Td>
                    <Td className="tabular text-[color:var(--color-ink-soft)]">
                      {formatDate(record.date)}
                    </Td>
                    <Td className="tabular text-[color:var(--color-ink-soft)]">
                      {record.checkInTime}
                    </Td>
                    <Td className="tabular text-[color:var(--color-ink-soft)]">
                      {record.checkOutTime}
                    </Td>
                    <Td>
                      <StatusBadge status={record.status} />
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

