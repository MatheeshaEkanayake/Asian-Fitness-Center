export default function EmptyState({ title, description, action }) {
  return (
    <div className="flex flex-col items-center justify-center text-center py-16 px-6">
      <div className="h-10 w-10 rounded-full border border-[color:var(--color-line-strong)] mb-4 flex items-center justify-center text-[color:var(--color-ink-faint)]">
        —
      </div>
      <h3 className="font-display text-base font-semibold text-[color:var(--color-ink)]">
        {title}
      </h3>
      {description && (
        <p className="mt-1 text-sm text-[color:var(--color-ink-soft)] max-w-sm">
          {description}
        </p>
      )}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}
