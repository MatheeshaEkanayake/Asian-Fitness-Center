import { useEffect, useState } from 'react'
import * as loginActivityService from '../../services/loginActivityService'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import EmptyState from '../../components/shared/EmptyState'
import Pagination from '../../components/shared/Pagination'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'
import { formatDateTime } from '../../utils/format'

// Read-only, single consumer — no Context, just local state + a direct
// service call (matches the "not every screen needs the full Context
// ceremony" simplification agreed in the implementation plan).

const PAGE_SIZE = 7
export default function LoginActivityPage() {
  const [page, setPage] = useState(1)
  const [result, setResult] = useState(null)
  const [status, setStatus] = useState('loading')

  useEffect(() => {
    setStatus('loading')
    loginActivityService
      .listLoginActivity(page, PAGE_SIZE)
      .then((data) => {
        setResult(data)
        setStatus('ready')
      })
      .catch(() => setStatus('error'))
  }, [page])

  return (
    <div>
      <PageHeader
        back={{ to: '/setup', label: 'Back to setup' }}
        title="Login Activity"
        description="Audit trail of successful sign-ins."
      />
      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading…</div>
        ) : !result || result.data.length === 0 ? (
          <EmptyState title="No login activity yet" />
        ) : (
          <>
            <Table>
              <THead>
                <Th>User</Th>
                <Th>IP address</Th>
                <Th>Signed in</Th>
              </THead>
              <TBody>
                {result.data.map((entry) => (
                  <Tr key={entry.id}>
                    <Td>
                      <div className="font-medium">{entry.user?.fullName || 'Unknown user'}</div>
                      <div className="text-xs text-[color:var(--color-ink-faint)]">{entry.user?.email}</div>
                    </Td>
                    <Td className="tabular">{entry.ipAddress || '—'}</Td>
                    <Td className="tabular text-[color:var(--color-ink-soft)]">{formatDateTime(entry.loggedInAt)}</Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
            <Pagination
              page={result.currentPage}
              pageCount={result.lastPage}
              onPageChange={setPage}
              totalItems={result.total}
              pageSize={result.perPage}
            />
          </>
        )}
      </Card>
    </div>
  )
}
