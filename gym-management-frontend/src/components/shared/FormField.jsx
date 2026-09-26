export default function FormField({ label, htmlFor, error, hint, required, children }) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={htmlFor} className="text-sm font-medium text-[color:var(--color-ink)]">
        {label}
        {required && <span className="text-[color:var(--color-danger)]"> *</span>}
      </label>
      {children}
      {error ? (
        <p className="text-xs text-[color:var(--color-danger)]">{error}</p>
      ) : hint ? (
        <p className="text-xs text-[color:var(--color-ink-faint)]">{hint}</p>
      ) : null}
    </div>
  )
}

export const fieldClasses =
  'w-full rounded-md border bg-[color:var(--color-surface)] px-3 py-2 text-sm text-[color:var(--color-ink)] focus:border-[color:var(--color-brand)] disabled:bg-[color:var(--color-paper)] disabled:text-[color:var(--color-ink-faint)]'

export function TextInput({ error, className = '', ...props }) {
  return (
    <input
      className={`${fieldClasses} ${error ? 'border-[color:var(--color-danger)]' : 'border-[color:var(--color-line-strong)]'} ${className}`}
      {...props}
    />
  )
}

export function TextArea({ error, className = '', ...props }) {
  return (
    <textarea
      className={`${fieldClasses} ${error ? 'border-[color:var(--color-danger)]' : 'border-[color:var(--color-line-strong)]'} ${className}`}
      {...props}
    />
  )
}

export function Select({ error, className = '', children, ...props }) {
  return (
    <select
      className={`${fieldClasses} ${error ? 'border-[color:var(--color-danger)]' : 'border-[color:var(--color-line-strong)]'} ${className}`}
      {...props}
    >
      {children}
    </select>
  )
}
