import { Link } from 'react-router-dom'

// `back` is an optional { to, label } pair rendered as a "← Back to …" link
// above the title, matching the back links on the detail pages.
export default function PageHeader({ title, description, actions, back }) {
  return (
    <div className="mb-6">
      {back && (
        <Link
          to={back.to}
          className="inline-block text-sm text-[color:var(--color-ink-soft)] hover:text-[color:var(--color-ink)] mb-4"
        >
          ← {back.label}
        </Link>
      )}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="font-display text-2xl font-semibold text-[color:var(--color-ink)]">
            {title}
          </h1>
          {description && (
            <p className="mt-1 text-sm text-[color:var(--color-ink-soft)] max-w-prose">
              {description}
            </p>
          )}
        </div>
        {actions && <div className="flex items-center gap-2">{actions}</div>}
      </div>
    </div>
  )
}
