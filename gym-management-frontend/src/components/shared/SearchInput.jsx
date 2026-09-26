export default function SearchInput({ value, onChange, placeholder = 'Search…', className = '' }) {
  return (
    <div className={`relative ${className}`}>
      <svg
        aria-hidden="true"
        viewBox="0 0 20 20"
        className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[color:var(--color-ink-faint)]"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.7"
      >
        <circle cx="9" cy="9" r="6" />
        <path d="M17 17l-3.8-3.8" strokeLinecap="round" />
      </svg>
      <input
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full rounded-md border border-[color:var(--color-line-strong)] bg-[color:var(--color-surface)] py-2 pl-9 pr-3 text-sm text-[color:var(--color-ink)] placeholder:text-[color:var(--color-ink-faint)] focus:border-[color:var(--color-brand)]"
      />
    </div>
  )
}
