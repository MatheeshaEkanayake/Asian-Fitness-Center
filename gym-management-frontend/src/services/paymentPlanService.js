// Backend: gym-management-backend/app/Http/Controllers/Api/PaymentPlanController.php
//   listPaymentPlans()                  → GET   /api/payment-plans
//   listPublicPaymentPlans()            → GET   /api/public/payment-plans (no auth; signup page)
//   createPaymentPlan(input)            → POST  /api/setup/payment-plans
//   updatePaymentPlan(id, updates)      → PUT   /api/setup/payment-plans/{id}
//   setPaymentPlanActive(id, isActive)  → PATCH /api/setup/payment-plans/{id}/status

import { apiClient } from './apiClient'

export function listPaymentPlans() {
  return apiClient.get('/payment-plans')
}

export function listPublicPaymentPlans() {
  return apiClient.get('/public/payment-plans')
}

export function createPaymentPlan(input) {
  return apiClient.post('/setup/payment-plans', input)
}

export function updatePaymentPlan(planId, updates) {
  return apiClient.put(`/setup/payment-plans/${planId}`, updates)
}

export function setPaymentPlanActive(planId, isActive) {
  return apiClient.patch(`/setup/payment-plans/${planId}/status`, { isActive })
}
