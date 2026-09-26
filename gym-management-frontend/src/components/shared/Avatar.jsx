import { initials } from '../../utils/format'

export default function Avatar({ name, size = 'md' }) {
  const sizes = {
    sm: 'h-7 w-7 text-xs',
    md: 'h-9 w-9 text-sm',
    lg: 'h-14 w-14 text-lg',
  }
  return (
    <div
      className={`flex items-center justify-center rounded-full bg-[color:var(--color-ink)] dark:bg-[color:var(--color-line-strong)] text-white font-display font-semibold shrink-0 ${sizes[size]}`}
    >
      {initials(name) || '—'}
    </div>
  )
}
