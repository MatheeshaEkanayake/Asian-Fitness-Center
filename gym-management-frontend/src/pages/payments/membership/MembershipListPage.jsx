import { useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { usePayments } from '../../../context/PaymentsContext'
import { useMembers } from '../../../context/MembersContext'
import PageHeader from '../../../components/shared/PageHeader'
import Card from '../../../components/shared/Card'
import Button from '../../../components/shared/Button'
import SearchInput from '../../../components/shared/SearchInput'
import { Select } from '../../../components/shared/FormField'
import StatusBadge from '../../../components/shared/StatusBadge'
import Pagination from '../../../components/shared/Pagination'
import EmptyState from '../../../components/shared/EmptyState'
import { Table, THead, Th, TBody, Tr, Td } from '../../../components/shared/Table'
import { formatCurrency, formatDate } from '../../../utils/format'

const PAGE_SIZE = 6

export default function MembershipListPage() {
  const { transactions, status } = usePayments()
  const { getMemberById } = useMembers()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()

  const memberFilterId = searchParams.get('memberId')
  const memberFilterName = memberFilterId ? getMemberById(memberFilterId)?.fullName : null

  const [query, setQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('All')
  const [methodFilter, setMethodFilter] = useState('All')
  const [typeFilter, setTypeFilter] = useState('All')
  const [page, setPage] = useState(1)

  const filtered = useMemo(() => {
    let list = transactions
    // NOTE: String(...) compare — memberFilterId comes from the URL query
    // string (always a string) while t.memberId is a real DB integer id.
    if (memberFilterId) list = list.filter((t) => String(t.memberId) === String(memberFilterId))
    if (statusFilter !== 'All') list = list.filter((t) => t.status === statusFilter)
    if (methodFilter !== 'All') list = list.filter((t) => t.method === methodFilter)
    if (typeFilter !== 'All') list = list.filter((t) => t.type === typeFilter)
    if (query.trim()) {
      const q = query.trim().toLowerCase()
      list = list.filter((t) => {
        const member = getMemberById(t.memberId)
        return (
          t.invoiceNumber.toLowerCase().includes(q) ||
          (member && member.fullName.toLowerCase().includes(q))
        )
      })
    }
    return list
  }, [transactions, memberFilterId, statusFilter, methodFilter, typeFilter, query, getMemberById])

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const paged = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  const clearMemberFilter = () => {
    const next = new URLSearchParams(searchParams)
    next.delete('memberId')
    setSearchParams(next)
  }

  const withPageReset = (fn) => (value) => {
    fn(value)
    setPage(1)
  }

  return (
    <div>
      <PageHeader
        title="Membership Payment"
        description="Every one-time and recurring payment, in one place."
        actions={<Button onClick={() => navigate('/payments/membership/new')}>Record payment</Button>}
      />

      {memberFilterName && (
        <div className="mb-4 flex items-center gap-2 text-sm">
          <span className="text-[color:var(--color-ink-soft)]">Filtering by member:</span>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-dark)] px-2.5 py-1">
            {memberFilterName}
            <button onClick={clearMemberFilter} aria-label="Clear member filter">
              ✕
            </button>
          </span>
        </div>
      )}

      <div className="flex flex-col gap-3 mb-4">
        <SearchInput
          value={query}
          onChange={withPageReset(setQuery)}
          placeholder="Search by member or invoice #…"
          className="w-full sm:w-72"
        />
        <div className="flex flex-wrap items-center gap-3">
        <Select value={statusFilter} onChange={(e) => withPageReset(setStatusFilter)(e.target.value)} className="w-auto!" aria-label="Filter by status">
          <option value="All">All statuses</option>
          <option value="Paid">Paid</option>
          <option value="Pending">Pending</option>
          <option value="Failed">Failed</option>
          <option value="Refunded">Refunded</option>
          <option value="PartiallyRefunded">Partially refunded</option>
        </Select>
        <Select value={methodFilter} onChange={(e) => withPageReset(setMethodFilter)(e.target.value)} className="w-auto!" aria-label="Filter by method">
          <option value="All">All methods</option>
          <option value="Cash">Cash</option>
          <option value="Card">Card</option>
          <option value="Bank Transfer">Bank transfer</option>
          <option value="Online">Online</option>
        </Select>
        <Select value={typeFilter} onChange={(e) => withPageReset(setTypeFilter)(e.target.value)} className="w-auto!" aria-label="Filter by type">
          <option value="All">All types</option>
          <option value="OneTime">One-time</option>
          <option value="Recurring">Recurring</option>
        </Select>
      </div>
    </div>

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading membership payments…</div>
        ) : filtered.length === 0 ? (
          <EmptyState
            title="No membership payments found"
            description="Try adjusting your filters, or record a new payment."
          />
        ) : (
          <>
            <Table>
              <THead>
                <Th>Invoice</Th>
                <Th>Member</Th>
                <Th>Date</Th>
                <Th>Amount</Th>
                <Th>Method</Th>
                <Th>Type</Th>
                <Th>Status</Th>
              </THead>
              <TBody>
                {paged.map((t) => {
                  const member = getMemberById(t.memberId)
                  return (
                    <Tr key={t.id} onClick={() => navigate(`/payments/membership/${t.id}`)}>
                      <Td className="tabular text-[color:var(--color-ink-soft)]">{t.invoiceNumber}</Td>
                      <Td>{member ? member.fullName : 'Unknown member'}</Td>
                      <Td className="tabular text-[color:var(--color-ink-soft)]">{formatDate(t.date)}</Td>
                      <Td className="tabular">{formatCurrency(t.amount)}</Td>
                      <Td className="text-[color:var(--color-ink-soft)]">{t.method}</Td>
                      <Td className="text-[color:var(--color-ink-soft)]">{t.type === 'OneTime' ? 'One-time' : 'Recurring'}</Td>
                      <Td>
                        <StatusBadge status={t.status} />
                      </Td>
                    </Tr>
                  )
                })}
              </TBody>
            </Table>
            <Pagination page={page} pageCount={pageCount} onPageChange={setPage} totalItems={filtered.length} pageSize={PAGE_SIZE} />
          </>
        )}
      </Card>
    </div>
  )
}
