export default function Pagination({ page, pageCount, onPageChange, totalItems, pageSize }) {
  if (pageCount <= 1) return null

  const start = (page - 1) * pageSize + 1
  const end = Math.min(page * pageSize, totalItems)

  return (
    <div className="flex items-center justify-between border-t border-[color:var(--color-line)] px-4 py-3 text-sm text-[color:var(--color-ink-soft)]">
      <span>
        Showing {start}–{end} of {totalItems}
      </span>
      <div className="flex items-center gap-1">
        <button
          onClick={() => onPageChange(page - 1)}
          disabled={page <= 1}
          className="rounded px-2.5 py-1 border border-[color:var(--color-line-strong)] disabled:opacity-40 hover:bg-[color:var(--color-paper)]"
        >
          Previous
        </button>
        <span className="px-2 tabular">
          {page} / {pageCount}
        </span>
        <button
          onClick={() => onPageChange(page + 1)}
          disabled={page >= pageCount}
          className="rounded px-2.5 py-1 border border-[color:var(--color-line-strong)] disabled:opacity-40 hover:bg-[color:var(--color-paper)]"
        >
          Next
        </button>
      </div>
    </div>
  )
}
