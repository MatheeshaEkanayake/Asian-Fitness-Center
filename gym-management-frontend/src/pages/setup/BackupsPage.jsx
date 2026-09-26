import { useState } from 'react'
import { useBackups } from '../../context/BackupsContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import EmptyState from '../../components/shared/EmptyState'
import ConfirmDialog from '../../components/shared/ConfirmDialog'
import { Table, THead, Th, TBody, Tr, Td } from '../../components/shared/Table'
import { formatDateTime } from '../../utils/format'

function formatBytes(bytes) {
  if (!bytes) return '—'
  const mb = bytes / 1024 ** 2
  return `${mb.toFixed(1)} MB`
}

export default function BackupsPage() {
  const { backups, status, createBackup, download, removeBackup } = useBackups()

  const [running, setRunning] = useState(false)
  const [pendingDelete, setPendingDelete] = useState(null)
  const [deleting, setDeleting] = useState(false)

  const handleRun = async () => {
    setRunning(true)
    try {
      await createBackup()
    } finally {
      setRunning(false)
    }
  }

  const handleDelete = async () => {
    setDeleting(true)
    try {
      await removeBackup(pendingDelete.file)
      setPendingDelete(null)
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div>
      <PageHeader
        back={{ to: '/setup', label: 'Back to setup' }}
        title="Backup and Restore"
        description="Create and manage full database + file backups."
        actions={
          <Button onClick={handleRun} disabled={running}>
            {running ? 'Running backup…' : 'Run backup now'}
          </Button>
        }
      />

      <Card>
        {status === 'loading' ? (
          <div className="py-16 text-center text-sm text-[color:var(--color-ink-soft)]">Loading backups…</div>
        ) : backups.length === 0 ? (
          <EmptyState title="No backups yet" description="Run a backup to create your first one." />
        ) : (
          <Table>
            <THead>
              <Th>File</Th>
              <Th>Size</Th>
              <Th>Created</Th>
              <Th></Th>
            </THead>
            <TBody>
              {backups.map((backup) => (
                <Tr key={backup.file}>
                  <Td className="font-medium">{backup.file}</Td>
                  <Td className="tabular">{formatBytes(backup.sizeBytes)}</Td>
                  <Td className="tabular text-[color:var(--color-ink-soft)]">{formatDateTime(backup.createdAt)}</Td>
                  <Td className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button variant="ghost" onClick={() => download(backup.file)}>
                        Download
                      </Button>
                      <Button variant="danger" onClick={() => setPendingDelete(backup)}>
                        Delete
                      </Button>
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
        title="Delete backup"
        description={`Delete "${pendingDelete?.file}"? This cannot be undone.`}
        confirmLabel="Delete"
        loading={deleting}
      />
    </div>
  )
}
