import { useEffect, useMemo, useState } from 'react'
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
import { listStaffAttendance } from '../../services/attendanceService'

const PAGE_SIZE = 6

// Staff check-ins recorded from the door device (first punch = check-in,
// last = check-out). Kept separate from member attendance because staff
// aren't members. Same filter/sort/paginate behaviour as MemberAttendencePage.
export default function StaffAttendancePage() {
  const [records, setRecords] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error

  const [query, setQuery] = useState('')
  const [sortBy, setSortBy] = useState('date')
  const [page, setPage] = useState(1)

  useEffect(() => {
    let cancelled = false
    listStaffAttendance()
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
    if (query.trim()) {
      const q = query.trim().toLowerCase()
      list = list.filter((a) => a.staffName.toLowerCase().includes(q) || a.date.toLowerCase().includes(q))
    }
    return [...list].sort((a, b) =>
      sortBy === 'name' ? a.staffName.localeCompare(b.staffName) : new Date(b.date) - new Date(a.date)
    )
  }, [records, query, sortBy])

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const paged = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  return (
    <div>
      <PageHeader
        title="Staff Attendance"
        description="Staff check-ins from the door device."
      />

      <div className="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center">
        <SearchInput
          value={query}
          onChange={(value) => {
            setQuery(value)
            setPage(1)
          }}
          placeholder="Search by name or date…"
          className="w-full sm:w-72"
        />
        <Select
          value={sortBy}
          onChange={(e) => setSortBy(e.target.value)}
          className="w-auto!"
          aria-label="Sort staff attendance"
        >
          <option value="date">Sort by date</option>
          <option value="name">Sort by name</option>
        </Select>
      </div>

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading staff attendance…</div>
        ) : status === 'error' ? (
          <EmptyState title="Couldn't load staff attendance" description="Please try again later." />
        ) : filtered.length === 0 ? (
          <EmptyState
            title="No staff attendance yet"
            description={
              records.length === 0
                ? 'Staff check-ins appear here once the door device is connected and syncing.'
                : 'Try a different search term.'
            }
          />
        ) : (
          <>
            <Table>
              <THead>
                <Th>Staff member</Th>
                <Th>Date</Th>
                <Th>Check In</Th>
                <Th>Check Out</Th>
                <Th>Status</Th>
              </THead>
              <TBody>
                {paged.map((record) => (
                  <Tr key={record.id}>
                    <Td>
                      <div className="flex items-center gap-3">
                        <Avatar name={record.staffName} size="sm" />
                        <div className="font-medium">{record.staffName}</div>
                      </div>
                    </Td>
                    <Td className="tabular text-[color:var(--color-ink-soft)]">{formatDate(record.date)}</Td>
                    <Td className="tabular text-[color:var(--color-ink-soft)]">{record.checkInTime || '—'}</Td>
                    <Td className="tabular text-[color:var(--color-ink-soft)]">{record.checkOutTime || '—'}</Td>
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
