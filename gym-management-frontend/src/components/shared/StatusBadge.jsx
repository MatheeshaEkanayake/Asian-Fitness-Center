const STATUS_STYLES = {
  Active: 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-dark)]',
  Paid: 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-dark)]',
  Processed: 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-dark)]',
  Present: 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-dark)]',

  Pending: 'bg-[color:var(--color-amber-soft)] text-[color:var(--color-amber)]',
  Suspended: 'bg-[color:var(--color-amber-soft)] text-[color:var(--color-amber)]',
  Guest: 'bg-[color:var(--color-amber-soft)] text-[color:var(--color-amber)]',
  Paused: 'bg-[color:var(--color-amber-soft)] text-[color:var(--color-amber)]',
  Overdue: 'bg-[color:var(--color-amber-soft)] text-[color:var(--color-amber)]',
  Late: 'bg-[color:var(--color-amber-soft)] text-[color:var(--color-amber)]',

  Failed: 'bg-[color:var(--color-danger-soft)] text-[color:var(--color-danger)]',
  Absent: 'bg-[color:var(--color-danger-soft)] text-[color:var(--color-danger)]',
  Unpaid: 'bg-[color:var(--color-danger-soft)] text-[color:var(--color-danger)]',

  Inactive: 'bg-[color:var(--color-slate-soft)] text-[color:var(--color-slate)]',
  Cancelled: 'bg-[color:var(--color-slate-soft)] text-[color:var(--color-slate)]',
  Refunded: 'bg-[color:var(--color-slate-soft)] text-[color:var(--color-slate)]',
  PartiallyRefunded: 'bg-[color:var(--color-slate-soft)] text-[color:var(--color-slate)]',
}

const STATUS_LABELS = {
  PartiallyRefunded: 'Partially refunded',
}

export default function StatusBadge({ status }) {
  const classes = STATUS_STYLES[status] || 'bg-[color:var(--color-slate-soft)] text-[color:var(--color-slate)]'
  const label = STATUS_LABELS[status] || status
  return (
    <span className={`inline-flex items-center gap-1.5 rounded px-2 py-0.5 text-xs font-medium ${classes}`}>
      <span className="h-1.5 w-1.5 rounded-full bg-current" />
      {label}
    </span>
  )
}
