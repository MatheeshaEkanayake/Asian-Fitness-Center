// Backend files:
//   gym-management-backend/app/Http/Controllers/Api/PaymentController.php
//   gym-management-backend/app/Http/Controllers/Api/PaymentSummaryController.php
//
// Function → Endpoint mapping:
//   listTransactions()                   → GET  /api/payments
//   getTransaction(transactionId)        → GET  /api/payments/{id}
//   listTransactionsForMember(memberId)  → GET  /api/members/{id}/payments
//   recordPayment(input)                 → POST /api/payments
//   markTransactionPaid(transactionId)   → PUT  /api/payments/{id}/mark-paid
//   getPaymentSummary()                  → GET  /api/payments/summary
//
// NOTE: this file previously operated on the in-memory src/services/db.js mock
// store; it now calls the real Laravel API via apiClient. The backend table/
// model is named `payments`/Payment (renamed from `transactions`/Transaction),
// but every function/parameter name here is kept as-is (transaction,
// transactionId, listTransactions, etc.) so PaymentsContext.jsx and every
// page/component that consumes this file needed no changes — only the
// endpoint paths moved from /api/transactions to /api/payments.

import { apiClient } from './apiClient'

export async function listTransactions() {
  const page = await apiClient.get('/payments')
  return page.data
}

export function getTransaction(transactionId) {
  return apiClient.get(`/payments/${transactionId}`)
}

export function listTransactionsForMember(memberId) {
  return apiClient.get(`/members/${memberId}/payments`)
}

export function recordPayment(input) {
  return apiClient.post('/payments', input)
}

export function markTransactionPaid(transactionId) {
  return apiClient.put(`/payments/${transactionId}/mark-paid`)
}

export function getPaymentSummary() {
  return apiClient.get('/payments/summary')
}
