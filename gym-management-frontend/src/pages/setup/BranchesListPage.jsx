import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useBranches } from '../../context/BranchesContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import SearchInput from '../../components/shared/SearchInput'
import EmptyState from '../../components/shared/EmptyState'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'

export default function BranchesListPage() {
  const { branches, status, removeBranch } = useBranches()
  const navigate = useNavigate()

  const [query, setQuery] = useState('')
  const [pendingDelete, setPendingDelete] = useState(null)
  const [deleting, setDeleting] = useState(false)

  const filtered = useMemo(() => {
    if (!query.trim()) return branches
    const q = query.trim().toLowerCase()
    return branches.filter((b) => b.name.toLowerCase().includes(q))
  }, [branches, query])

  const handleDelete = async () => {
    setDeleting(true)
    try {
      await removeBranch(pendingDelete.id)
      setPendingDelete(null)
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div>
      <PageHeader
        back={{ to: '/setup', label: 'Back to setup' }}
        title="Branches"
        description="Manage gym locations. Kept branch-aware for future expansion."
        actions={<Button onClick={() => navigate('/setup/branches/new')}>Add branch</Button>}
      />

      <div className="mb-4">
        <SearchInput value={query} onChange={setQuery} placeholder="Search branches…" className="w-full sm:w-72" />
      </div>

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading branches…</div>
        ) : filtered.length === 0 ? (
          <EmptyState
            title="No branches found"
            description={branches.length === 0 ? 'Add your first branch to get started.' : 'Try a different search term.'}
          />
        ) : (
          <Table>
            <THead>
              <Th>Name</Th>
              <Th>Address</Th>
              <Th>Phone</Th>
              <Th></Th>
            </THead>
            <TBody>
              {filtered.map((branch) => (
                <Tr key={branch.id}>
                  <Td>
                    <div className="font-medium">{branch.name}</div>
                    {branch.isDefault && (
                      <div className="text-xs text-[color:var(--color-ink-faint)]">Default branch</div>
                    )}
                  </Td>
                  <Td className="text-[color:var(--color-ink-soft)]">{branch.address || '—'}</Td>
                  <Td className="tabular">{branch.phone || '—'}</Td>
                  <Td className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button variant="ghost" onClick={() => navigate(`/setup/branches/${branch.id}/edit`)}>
                        Edit
                      </Button>
                      {!branch.isDefault && (
                        <Button variant="danger" onClick={() => setPendingDelete(branch)}>
                          Delete
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
        open={Boolean(pendingDelete)}
        onClose={() => setPendingDelete(null)}
        onConfirm={handleDelete}
        title="Delete branch"
        description={`Delete "${pendingDelete?.name}"? This cannot be undone.`}
        confirmLabel="Delete"
        loading={deleting}
      />
    </div>
  )
}
