import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useRoles } from '../../context/RolesContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import EmptyState from '../../components/shared/EmptyState'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'

export default function RolesListPage() {
  const { roles, status, removeRole } = useRoles()
  const navigate = useNavigate()

  const [pendingDelete, setPendingDelete] = useState(null)
  const [deleting, setDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const handleDelete = async () => {
    setDeleting(true)
    setDeleteError('')
    try {
      await removeRole(pendingDelete.id)
      setPendingDelete(null)
    } catch (err) {
      setDeleteError(err.message)
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div>
      <PageHeader
        back={{ to: '/setup', label: 'Back to setup' }}
        title="Roles"
        description="Define what each role can see and do. Admin roles bypass all permission checks."
        actions={<Button onClick={() => navigate('/setup/roles/new')}>Add role</Button>}
      />

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading roles…</div>
        ) : roles.length === 0 ? (
          <EmptyState title="No roles found" description="Add your first role to get started." />
        ) : (
          <Table>
            <THead>
              <Th>Name</Th>
              <Th>Description</Th>
              <Th>Type</Th>
              <Th></Th>
            </THead>
            <TBody>
              {roles.map((role) => (
                <Tr key={role.id}>
                  <Td className="font-medium">{role.name}</Td>
                  <Td className="text-[color:var(--color-ink-soft)]">{role.description || '—'}</Td>
                  <Td>{role.isAdmin ? 'Administrator (full access)' : 'Standard'}</Td>
                  <Td className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button variant="ghost" onClick={() => navigate(`/setup/roles/${role.id}/edit`)}>
                        Edit
                      </Button>
                      {!role.isSystem && (
                        <Button variant="danger" onClick={() => setPendingDelete(role)}>
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
        onClose={() => {
          setPendingDelete(null)
          setDeleteError('')
        }}
        onConfirm={handleDelete}
        title="Delete role"
        description={deleteError || `Delete "${pendingDelete?.name}"? This cannot be undone.`}
        confirmLabel="Delete"
        loading={deleting}
      />
    </div>
  )
}
