import { useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useRoles } from '../../context/RolesContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import FormField, { TextInput, TextArea } from '../../components/shared/FormField'
import PermissionsChecklist from '../../components/setup/PermissionsChecklist'

const emptyForm = { name: '', description: '', permissions: [], isAdmin: false }

function validate(form) {
  const errors = {}
  if (!form.name.trim()) errors.name = 'Role name is required.'
  return errors
}

export default function RoleFormPage() {
  const { roleId } = useParams()
  const isEdit = Boolean(roleId)
  const navigate = useNavigate()
  const { getRoleById, addRole, editRole } = useRoles()

  const existing = isEdit ? getRoleById(Number(roleId)) : null
  const toFormShape = (record) => ({
    ...emptyForm,
    name: record.name,
    description: record.description ?? '',
    permissions: record.permissions ?? [],
    isAdmin: record.isAdmin,
  })
  const [form, setForm] = useState(() => (existing ? toFormShape(existing) : emptyForm))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const title = isEdit ? 'Edit role' : 'Add role'
  const isSystem = Boolean(existing?.isSystem)

  const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }))

  const baseline = useMemo(() => (isEdit && existing ? toFormShape(existing) : emptyForm), [isEdit, existing])
  const isDirty = useMemo(() => JSON.stringify(form) !== JSON.stringify(baseline), [form, baseline])

  const handleCancel = () => {
    if (isDirty && !window.confirm('Discard unsaved changes?')) return
    navigate('/setup/roles')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = validate(form)
    setErrors(validationErrors)
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      if (isEdit) {
        await editRole(Number(roleId), form)
      } else {
        await addRole(form)
      }
      navigate('/setup/roles')
    } finally {
      setSaving(false)
    }
  }

  if (isEdit && !existing) {
    return <Card className="p-8 text-center text-sm text-[color:var(--color-ink-soft)]">Role not found.</Card>
  }

  return (
    <div className="max-w-lg">
      <PageHeader title={title} description="Admin roles bypass every permission check below." />
      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-5">
          <FormField label="Name" htmlFor="name" required error={errors.name}>
            <TextInput id="name" value={form.name} onChange={update('name')} error={errors.name} disabled={isSystem} />
          </FormField>
          <FormField label="Description" htmlFor="description">
            <TextArea id="description" rows={2} value={form.description} onChange={update('description')} />
          </FormField>

          <label className="flex items-center gap-2 text-sm text-[color:var(--color-ink)]">
            <input
              type="checkbox"
              checked={form.isAdmin}
              disabled={isSystem}
              onChange={(e) => setForm((f) => ({ ...f, isAdmin: e.target.checked }))}
            />
            Administrator (bypasses all permission checks)
          </label>

          {!form.isAdmin && (
            <div>
              <h3 className="text-sm font-medium text-[color:var(--color-ink)] mb-1">Permissions</h3>
              <PermissionsChecklist
                value={form.permissions}
                onChange={(permissions) => setForm((f) => ({ ...f, permissions }))}
              />
            </div>
          )}

          <div className="flex justify-end gap-2 pt-2 border-t border-[color:var(--color-line)]">
            <Button type="button" variant="secondary" onClick={handleCancel} disabled={saving}>
              Cancel
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save role'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}
