const VARIANTS = {
  primary:
    'bg-[color:var(--color-brand)] text-[color:var(--color-on-brand)] hover:bg-[color:var(--color-brand-dark)] border border-transparent',
  secondary:
    'bg-transparent text-[color:var(--color-ink)] border border-[color:var(--color-line-strong)] hover:bg-[color:var(--color-paper)]',
  danger:
    'bg-transparent text-[color:var(--color-danger)] border border-[color:var(--color-danger)]/40 hover:bg-[color:var(--color-danger-soft)]',
  ghost:
    'bg-transparent text-[color:var(--color-ink-soft)] border border-transparent hover:bg-[color:var(--color-paper)]',
}

export default function Button({
  variant = 'primary',
  className = '',
  type = 'button',
  ...props
}) {
  return (
    <button
      type={type}
      className={`inline-flex items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${VARIANTS[variant]} ${className}`}
      {...props}
    />
  )
}
