// Mounted in App.jsx's CoreProviders (not SetupProviders) because the member
// form and payment form both pick a plan, not just Setup > Payment Plans.

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import * as paymentPlanService from '../services/paymentPlanService'
import { useToast } from './ToastContext'
import { useMembers } from './MembersContext'
import { formatPlan } from '../utils/format'

const PaymentPlansContext = createContext(null)

export function PaymentPlansProvider({ children }) {
  const [plans, setPlans] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error
  const { showToast } = useToast()
  const { reload: reloadMembers } = useMembers()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      setPlans(await paymentPlanService.listPaymentPlans())
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  const addPlan = useCallback(
    async (input) => {
      const plan = await paymentPlanService.createPaymentPlan(input)
      // Re-fetch rather than append so the server's Daily → Monthly-by-length
      // ordering is kept.
      await reload()
      showToast(`${formatPlan(plan)} plan added.`)
      return plan
    },
    [reload, showToast]
  )

  const editPlan = useCallback(
    async (planId, updates) => {
      const updated = await paymentPlanService.updatePaymentPlan(planId, updates)
      // Re-fetch so the list re-sorts if the type/length changed, and reload
      // members so their embedded plan shows the new price/length.
      await Promise.all([reload(), reloadMembers()])
      showToast(`${formatPlan(updated)} plan updated.`)
      return updated
    },
    [reload, reloadMembers, showToast]
  )

  const setPlanActive = useCallback(
    async (planId, isActive) => {
      const updated = await paymentPlanService.setPaymentPlanActive(planId, isActive)
      setPlans((current) => current.map((p) => (p.id === planId ? updated : p)))
      showToast(`${updated.name} plan ${isActive ? 'reactivated' : 'retired'}.`)
      return updated
    },
    [showToast]
  )

  const activePlans = useMemo(() => plans.filter((p) => p.isActive), [plans])

  const getPlanById = useCallback(
    (planId) => plans.find((p) => String(p.id) === String(planId)),
    [plans]
  )

  return (
    <PaymentPlansContext.Provider
      value={{ plans, activePlans, status, reload, addPlan, editPlan, setPlanActive, getPlanById }}
    >
      {children}
    </PaymentPlansContext.Provider>
  )
}

export function usePaymentPlans() {
  const ctx = useContext(PaymentPlansContext)
  if (!ctx) throw new Error('usePaymentPlans must be used within PaymentPlansProvider')
  return ctx
}
