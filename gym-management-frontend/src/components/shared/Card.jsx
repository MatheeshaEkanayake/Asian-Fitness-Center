export default function Card({ className = '', children, ...props }) {
  return (
    <div
      className={`bg-[color:var(--color-surface)] border border-[color:var(--color-line)] rounded-lg ${className}`}
      {...props}
    >
      {children}
    </div>
  )
}
