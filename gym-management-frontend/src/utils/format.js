// =============================================================================
// BACKEND NOTE — formatting helpers remain on the frontend.
//
// These functions format data for display and do not need a backend equivalent.
// However, date *generation* functions have been moved to Laravel:
//
//   todayISO()        → Carbon::now()->toDateString()   (used in controllers)
//   addToDate()       → Carbon date arithmetic          (if needed in controllers)
//
// The following functions stay in the frontend (display-only):
//   formatCurrency()  — formats LKR amounts for display
//   formatDate()      — formats ISO date strings for display
//   formatDateTime()  — formats ISO datetime strings for display
//   initials()        — derives avatar initials from a name
//   formatPlan()      — "3 months · Rs 13,500" label for a payment plan
//
// Backend: no equivalent file needed.
// =============================================================================

export function formatCurrency(amount) {
  const value = Number(amount) || 0
  return new Intl.NumberFormat('si-LK', {
    currencyDisplay: "narrowSymbol",
    style: 'currency',
    currency: 'LKR',
    minimumFractionDigits: 2,
  }).format(value)
}

export function formatDate(isoString) {
  if (!isoString) return '—'
  const date = new Date(isoString)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(date)
}

export function formatDateTime(isoString) {
  if (!isoString) return '—'
  const date = new Date(isoString)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date)
}

export function todayISO() {
  return new Date().toISOString().slice(0, 10)
}

export function addToDate(isoDate, { months = 0 } = {}) {
  const date = new Date(isoDate)
  date.setMonth(date.getMonth() + months)
  return date.toISOString().slice(0, 10)
}

export function initials(name = '') {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

export function formatPlan(plan) {
  if (!plan) return ''
  return `${plan.name} · ${formatCurrency(plan.amount)}`
}
