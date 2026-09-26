// Renders the canonical permission-key list (mirrors the backend's
// App\Support\Permissions::ALL) grouped by resource, with paired View/Edit
// checkboxes where both exist. Unchecking View also revokes Edit (can't
// edit what you can't view); checking Edit implies View.

const GROUPS = [
  { label: 'Dashboard', view: 'dashboard.view' },
  { label: 'Members', view: 'members.view', edit: 'members.edit' },
  { label: 'Member Review', view: 'members.review', edit: 'members.review_edit' },
  { label: 'Attendance', view: 'attendance.view', edit: 'attendance.edit' },
  { label: 'Payments', view: 'payments.view', edit: 'payments.edit' },
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
      onChange(value.filter((k) => k !== group.view && k !== group.edit))
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

  return (
    <div className="divide-y divide-[color:var(--color-line)]">
      {GROUPS.map((group) => (
        <div key={group.label} className="flex items-center justify-between py-3">
          <span className="text-sm font-medium text-[color:var(--color-ink)]">{group.label}</span>
          <div className="flex items-center gap-5">
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
          </div>
        </div>
      ))}
    </div>
  )
}
