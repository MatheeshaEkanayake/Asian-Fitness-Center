// Backend controllers:
//   gym-management-backend/app/Http/Controllers/Api/PaymentController.php
//   gym-management-backend/app/Http/Controllers/Api/PaymentSummaryController.php
//
// NOTE: paymentService.js now hits the real API, which returns auto-increment
// integer ids (was string ids like 'txn_3001'/'mem_1001' under the old mock).
// Route params and query strings are always strings, so every id comparison
// below uses String(...) on both sides to avoid a number/string mismatch.

import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as paymentService from '../services/paymentService'
import { useToast } from './ToastContext'

const PaymentsContext = createContext(null)

export function PaymentsProvider({ children }) {
  const [transactions, setTransactions] = useState([])
  const [summary, setSummary] = useState(null)
  const [status, setStatus] = useState('loading')
  const { showToast } = useToast()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      const [txns, summaryData] = await Promise.all([
        paymentService.listTransactions(),
        paymentService.getPaymentSummary(),
      ])
      setTransactions(txns)
      setSummary(summaryData)
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  const refreshSummary = useCallback(async () => {
    setSummary(await paymentService.getPaymentSummary())
  }, [])

  const recordPayment = useCallback(
    async (input) => {
      const transaction = await paymentService.recordPayment(input)
      setTransactions((current) => [transaction, ...current])
      await refreshSummary()
      showToast(`Payment ${transaction.invoiceNumber} recorded.`)
      return transaction
    },
    [refreshSummary, showToast]
  )

  const deleteTransaction = useCallback(
    async (transaction) => {
      await paymentService.deleteTransaction(transaction.id)
      setTransactions((current) => current.filter((t) => String(t.id) !== String(transaction.id)))
      await refreshSummary()
      showToast(`Payment ${transaction.invoiceNumber} deleted.`)
    },
    [refreshSummary, showToast]
  )

  const markTransactionPaid = useCallback(
    async (transactionId) => {
      const updated = await paymentService.markTransactionPaid(transactionId)
      setTransactions((current) =>
        current.map((t) => (String(t.id) === String(transactionId) ? updated : t))
      )
      await refreshSummary()
      showToast(`${updated.invoiceNumber} marked as paid.`)
      return updated
    },
    [refreshSummary, showToast]
  )

  const getTransactionById = useCallback(
    (transactionId) => transactions.find((t) => String(t.id) === String(transactionId)),
    [transactions]
  )

  const getTransactionsForMember = useCallback(
    (memberId) => transactions.filter((t) => String(t.memberId) === String(memberId)),
    [transactions]
  )

  return (
    <PaymentsContext.Provider
      value={{
        transactions,
        summary,
        status,
        reload,
        recordPayment,
        markTransactionPaid,
        deleteTransaction,
        getTransactionById,
        getTransactionsForMember,
      }}
    >
      {children}
    </PaymentsContext.Provider>
  )
}

export function usePayments() {
  const ctx = useContext(PaymentsContext)
  if (!ctx) throw new Error('usePayments must be used within PaymentsProvider')
  return ctx
}
