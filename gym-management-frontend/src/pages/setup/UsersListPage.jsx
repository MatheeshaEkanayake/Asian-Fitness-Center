import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useUsers } from '../../context/UsersContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import SearchInput from '../../components/shared/SearchInput'
import StatusBadge from '../../components/shared/StatusBadge'
import Avatar from '../../components/shared/Avatar'
import EmptyState from '../../components/shared/EmptyState'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'

export default function UsersListPage() {
  const { users, status, deactivateUser } = useUsers()
  const navigate = useNavigate()

  const [query, setQuery] = useState('')
  const [pendingDeactivate, setPendingDeactivate] = useState(null)
  const [working, setWorking] = useState(false)

  const filtered = useMemo(() => {
    if (!query.trim()) return users
    const q = query.trim().toLowerCase()
    return users.filter((u) =>
      [u.fullName, u.email, u.username].some((field) => field?.toLowerCase().includes(q))
    )
  }, [users, query])

  const handleDeactivate = async () => {
    setWorking(true)
    try {
      await deactivateUser(pendingDeactivate.id)
      setPendingDeactivate(null)
    } finally {
      setWorking(false)
    }
  }

  return (
    <div>
      <PageHeader
        back={{ to: '/setup', label: 'Back to setup' }}
        title="Users"
        description="Staff accounts. People register on the sign-up page first, then you grant them a role here."
        actions={<Button onClick={() => navigate('/setup/users/new')}>Grant role</Button>}
      />

      <div className="mb-4">
        <SearchInput value={query} onChange={setQuery} placeholder="Search by name, username or email…" className="w-full sm:w-72" />
      </div>

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading users…</div>
        ) : filtered.length === 0 ? (
          <EmptyState
            title="No users found"
            description={users.length === 0 ? 'Grant a registered person a role to get started.' : 'Try a different search term.'}
          />
        ) : (
          <Table>
            <THead>
              <Th>User</Th>
              <Th>Role</Th>
              <Th>Branch</Th>
              <Th>Status</Th>
              <Th></Th>
            </THead>
            <TBody>
              {filtered.map((user) => (
                <Tr key={user.id}>
                  <Td>
                    <div className="flex items-center gap-3">
                      <Avatar name={user.fullName} size="sm" />
                      <div>
                        <div className="font-medium">{user.fullName}</div>
                        <div className="text-xs text-[color:var(--color-ink-faint)]">
                          {[user.username && `@${user.username}`, user.email].filter(Boolean).join(' · ')}
                        </div>
                      </div>
                    </div>
                  </Td>
                  <Td>{user.role?.name || '—'}</Td>
                  <Td>{user.branch?.name || '—'}</Td>
                  <Td>
                    <StatusBadge status={user.status} />
                  </Td>
                  <Td className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button variant="ghost" onClick={() => navigate(`/setup/users/${user.id}/edit`)}>
                        Edit
                      </Button>
                      {user.status === 'Active' && (
                        <Button variant="danger" onClick={() => setPendingDeactivate(user)}>
                          Deactivate
                        </Button>
                      )}
                    </div>
                  </Td>
                </Tr>
              ))}
            </TBody>
          </Table>
        )}
      </Card>

      <ConfirmDialog
        open={Boolean(pendingDeactivate)}
        onClose={() => setPendingDeactivate(null)}
        onConfirm={handleDeactivate}
        title="Deactivate user"
        description={`Deactivate "${pendingDeactivate?.fullName}"? They will no longer be able to sign in.`}
        confirmLabel="Deactivate"
        loading={working}
      />
    </div>
  )
}
