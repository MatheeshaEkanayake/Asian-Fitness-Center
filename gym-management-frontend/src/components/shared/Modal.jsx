import { useEffect } from 'react'

export default function Modal({ open, onClose, title, children, width = 'max-w-lg' }) {
  useEffect(() => {
    if (!open) return
    const onKeyDown = (e) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center p-4">
      <button
        aria-label="Close dialog"
        onClick={onClose}
        className="absolute inset-0 bg-[color:var(--color-ink)]/40 dark:bg-black/60"
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        className={`relative w-full ${width} rounded-lg bg-[color:var(--color-surface)] border border-[color:var(--color-line)] shadow-xl max-h-[90vh] overflow-y-auto`}
      >
        <div className="flex items-center justify-between px-5 py-4 border-b border-[color:var(--color-line)]">
          <h2 id="modal-title" className="font-display text-lg font-semibold text-[color:var(--color-ink)]">
            {title}
          </h2>
          <button
            onClick={onClose}
            aria-label="Close"
            className="text-[color:var(--color-ink-faint)] hover:text-[color:var(--color-ink)]"
          >
            ✕
          </button>
        </div>
        <div className="p-5">{children}</div>
      </div>
    </div>
  )
}
