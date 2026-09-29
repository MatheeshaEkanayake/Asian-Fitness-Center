import { createPortal } from 'react-dom'
import Modal from '../shared/Modal'
import Button from '../shared/Button'
import { formatCurrency, formatDate } from '../../utils/format'
import { useToast } from '../../context/ToastContext'
import { useBranding } from '../../context/BrandingContext'

export default function ReceiptView({ open, onClose, transaction, member }) {
  const { showToast } = useToast()
  const { branding } = useBranding()
  if (!transaction) return null

  const contactLine = [branding.address, branding.phone, branding.email].filter(Boolean).join(' · ')

  const handleEmail = () => {
    showToast(`Receipt emailed to ${member?.email || 'member'}.`)
  }

  return (
    <Modal open={open} onClose={onClose} title="Receipt" width="max-w-md">
      <Receipt transaction={transaction} member={member} branding={branding} contactLine={contactLine} />

      <div className="mt-5 flex justify-end gap-2">
        <Button variant="secondary" onClick={handleEmail}>
          Email receipt
        </Button>
        <Button onClick={printOnThermal}>Print / Download</Button>
      </div>

      {/* Printed copy, outside the modal so its scroll box can't clip it
          (see the print rules in index.css). Hidden on screen. */}
      {createPortal(
        <div id="receipt-printable">
          <Receipt transaction={transaction} member={member} branding={branding} contactLine={contactLine} />
        </div>,
        document.body
      )}
    </Modal>
  )
}

// 80 mm roll: page as long as the receipt so the printer doesn't feed a
// full sheet. The printed copy is hidden on screen, so show it off-screen
// for a moment to measure it.
function printOnThermal() {
  const copy = document.getElementById('receipt-printable')
  copy.style.cssText = 'display:block;position:absolute;left:-10000px;top:0'
  const heightMm = Math.ceil((copy.offsetHeight * 25.4) / 96) + 4
  copy.style.cssText = ''

  const pageStyle = document.createElement('style')
  pageStyle.textContent = `@page { size: 80mm ${heightMm}mm; margin: 0; }`
  document.head.appendChild(pageStyle)
  window.print()
  pageStyle.remove()
}

function Receipt({ transaction, member, branding, contactLine }) {
  return (
    <div className="receipt border border-[color:var(--color-line)] rounded-md p-5">
      <div className="receipt-head flex items-start justify-between gap-3 pb-3 border-b border-[color:var(--color-line)]">
        <div className="receipt-brand flex items-center gap-3 min-w-0">
          <img src={branding.logoUrl} alt="" className="receipt-logo h-10 w-10 shrink-0 rounded object-contain" />
          <div className="min-w-0">
            <div className="font-display font-bold text-[color:var(--color-ink)]">{branding.name}</div>
            {contactLine && <div className="text-xs text-[color:var(--color-ink-faint)]">{contactLine}</div>}
          </div>
        </div>
        <span className="receipt-invoice shrink-0 text-xs text-[color:var(--color-ink-faint)]">{transaction.invoiceNumber}</span>
      </div>
      <div className="receipt-rows mt-4 space-y-2 text-sm">
        <Row label="Billed to" value={member ? member.fullName : 'Unknown member'} />
        <Row label="Date" value={formatDate(transaction.date)} />
        <Row label="Method" value={transaction.method} />
        <Row label="Type" value={transaction.type === 'OneTime' ? 'One-time' : 'Recurring'} />
        <Row label="Status" value={transaction.status} />
      </div>
      <div className="receipt-total mt-4 pt-4 border-t border-[color:var(--color-line)] flex items-center justify-between">
        <span className="text-sm font-medium text-[color:var(--color-ink)]">Total</span>
        <span className="font-display text-lg font-semibold tabular">{formatCurrency(transaction.amount)}</span>
      </div>
    </div>
  )
}

function Row({ label, value }) {
  return (
    <div className="flex justify-between gap-3">
      <span className="text-[color:var(--color-ink-soft)]">{label}</span>
      <span className="text-right text-[color:var(--color-ink)]">{value}</span>
    </div>
  )
}
