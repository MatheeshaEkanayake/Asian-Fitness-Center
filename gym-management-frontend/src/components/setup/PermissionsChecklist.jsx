// Renders the canonical permission-key list (mirrors the backend's
// App\Support\Permissions::ALL) grouped by resource, with paired View/Edit
// checkboxes where both exist. Unchecking View also revokes Edit and any
// extras (can't edit what you can't view); checking Edit or an extra
// implies View. Extras are single actions granted on their own, e.g.
// Members > Gate access / Delete on the member list.

const GROUPS = [
  { label: 'Dashboard', view: 'dashboard.view' },
  {
    label: 'Members',
    view: 'members.view',
    edit: 'members.edit',
    extras: [
      { key: 'members.gate_access', label: 'Gate access' },
      { key: 'members.delete', label: 'Delete' },
    ],
  },
  { label: 'Member Review', view: 'members.review', edit: 'members.review_edit' },
  { label: 'Attendance', view: 'attendance.view', edit: 'attendance.edit' },
  {
    label: 'Payments',
    view: 'payments.view',
    edit: 'payments.edit',
    extras: [{ key: 'payments.delete', label: 'Delete' }],
  },
  { label: 'Payments Audit', view: 'payments_audit.view', edit: 'payments_audit_edit' },
  { label: 'Inventory', view: 'inventory.view', edit: 'inventory_edit' },
  { label: 'Stock', view: 'stock.view', edit: 'stock_edit' },
  { label: 'Dealers', view: 'dealers.view', edit: 'dealers_edit' },
  { label: 'Inventory Audit', view: 'inventory_audit.view', edit: 'inventory_audit_edit' },
  { label: 'Exercise Plan', view: 'exercise_plan.view', edit: 'exercise_plan_edit' },
  { label: 'Meal Plan', view: 'meal_plan.view', edit: 'meal_plan_edit' },
  { label: 'Setup', view: 'setup.manage' },
]

export default function PermissionsChecklist({ value = [], onChange }) {
  const has = (key) => value.includes(key)

  const toggleView = (group) => {
    if (has(group.view)) {
      const dependents = [group.view, group.edit, ...(group.extras ?? []).map((x) => x.key)]
      onChange(value.filter((k) => !dependents.includes(k)))
    } else {
      onChange([...value, group.view])
    }
  }

  const toggleEdit = (group) => {
    if (has(group.edit)) {
      onChange(value.filter((k) => k !== group.edit))
    } else {
      onChange([...new Set([...value, group.view, group.edit])])
    }
  }

  const toggleExtra = (group, key) => {
    if (has(key)) {
      onChange(value.filter((k) => k !== key))
    } else {
      onChange([...new Set([...value, group.view, key])])
    }
  }

  return (
    <div className="divide-y divide-[color:var(--color-line)]">
      {GROUPS.map((group) => (
        <div key={group.label} className="flex items-center justify-between py-3">
          <span className="text-sm font-medium text-[color:var(--color-ink)]">{group.label}</span>
          <div className="flex flex-wrap items-center justify-end gap-x-5 gap-y-2">
            <label className="flex items-center gap-2 text-sm text-[color:var(--color-ink-soft)]">
              <input type="checkbox" checked={has(group.view)} onChange={() => toggleView(group)} />
              View
            </label>
            {group.edit && (
              <label className="flex items-center gap-2 text-sm text-[color:var(--color-ink-soft)]">
                <input type="checkbox" checked={has(group.edit)} onChange={() => toggleEdit(group)} />
                Edit
              </label>
            )}
            {group.extras?.map((extra) => (
              <label key={extra.key} className="flex items-center gap-2 text-sm text-[color:var(--color-ink-soft)]">
                <input type="checkbox" checked={has(extra.key)} onChange={() => toggleExtra(group, extra.key)} />
                {extra.label}
              </label>
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}
