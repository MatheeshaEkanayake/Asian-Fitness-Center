export function Table({ children }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm border-collapse">{children}</table>
    </div>
  )
}

export function THead({ children }) {
  return (
    <thead className="border-b border-[color:var(--color-line)] text-left">
      <tr>{children}</tr>
    </thead>
  )
}

export function Th({ children, className = '' }) {
  return (
    <th className={`px-4 py-3 font-medium text-[color:var(--color-ink-soft)] ${className}`}>
      {children}
    </th>
  )
}

export function TBody({ children }) {
  return <tbody className="divide-y divide-[color:var(--color-line)]">{children}</tbody>
}

export function Tr({ children, onClick, className = '' }) {
  return (
    <tr
      onClick={onClick}
      className={`${onClick ? 'cursor-pointer hover:bg-[color:var(--color-paper)]' : ''} ${className}`}
    >
      {children}
    </tr>
  )
}

export function Td({ children, className = '' }) {
  return <td className={`px-4 py-3 align-middle text-[color:var(--color-ink)] ${className}`}>{children}</td>
}
