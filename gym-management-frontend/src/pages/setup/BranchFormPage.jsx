import { useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useBranches } from '../../context/BranchesContext'
import PageHeader from '../../components/shared/PageHeader'
import Card from '../../components/shared/Card'
import Button from '../../components/shared/Button'
import FormField, { TextInput } from '../../components/shared/FormField'

const emptyForm = { name: '', address: '', phone: '' }

function validate(form) {
  const errors = {}
  if (!form.name.trim()) errors.name = 'Branch name is required.'
  return errors
}

export default function BranchFormPage() {
  const { branchId } = useParams()
  const isEdit = Boolean(branchId)
  const navigate = useNavigate()
  const { getBranchById, addBranch, editBranch } = useBranches()

  const existing = isEdit ? getBranchById(Number(branchId)) : null
  const toFormShape = (record) => ({ ...emptyForm, ...record, address: record.address ?? '', phone: record.phone ?? '' })
  const [form, setForm] = useState(() => (existing ? toFormShape(existing) : emptyForm))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const title = isEdit ? 'Edit branch' : 'Add branch'

  const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }))

  const baseline = useMemo(() => (isEdit && existing ? toFormShape(existing) : emptyForm), [isEdit, existing])
  const isDirty = useMemo(() => JSON.stringify(form) !== JSON.stringify(baseline), [form, baseline])

  const handleCancel = () => {
    if (isDirty && !window.confirm('Discard unsaved changes?')) return
    navigate('/setup/branches')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    const validationErrors = validate(form)
    setErrors(validationErrors)
    if (Object.keys(validationErrors).length > 0) return

    setSaving(true)
    try {
      if (isEdit) {
        await editBranch(Number(branchId), form)
      } else {
        await addBranch(form)
      }
      navigate('/setup/branches')
    } finally {
      setSaving(false)
    }
  }

  if (isEdit && !existing) {
    return (
      <Card className="p-8 text-center text-sm text-[color:var(--color-ink-soft)]">Branch not found.</Card>
    )
  }

  return (
    <div className="max-w-lg">
      <PageHeader title={title} description="Branches are single-location today but kept as real records for future expansion." />
      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-5">
          <FormField label="Name" htmlFor="name" required error={errors.name}>
            <TextInput id="name" value={form.name} onChange={update('name')} error={errors.name} placeholder="Main Branch" />
          </FormField>
          <FormField label="Address" htmlFor="address">
            <TextInput id="address" value={form.address} onChange={update('address')} />
          </FormField>
          <FormField label="Phone" htmlFor="phone">
            <TextInput id="phone" value={form.phone} onChange={update('phone')} />
          </FormField>

          <div className="flex justify-end gap-2 pt-2 border-t border-[color:var(--color-line)]">
            <Button type="button" variant="secondary" onClick={handleCancel} disabled={saving}>
              Cancel
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save branch'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  )
}
