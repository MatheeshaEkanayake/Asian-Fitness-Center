export default function ForbiddenPage() {
  return (
    <div className="py-16 text-center">
      <p className="font-display text-xl font-semibold text-[color:var(--color-ink)]">Access denied</p>
      <p className="mt-1 text-sm text-[color:var(--color-ink-soft)]">
        You don't have permission to view this page. Contact an administrator if you think this is a mistake.
      </p>
    </div>
  )
}
