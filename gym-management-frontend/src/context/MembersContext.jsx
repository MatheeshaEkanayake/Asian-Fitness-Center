// Backend controller: gym-management-backend/app/Http/Controllers/Api/MemberController.php
//
// NOTE: memberService.js now hits the real API, which returns auto-increment
// integer ids (was string ids like 'mem_1001' under the old mock). Route
// params (useParams()) and query strings are always strings, so every id
// comparison below uses String(...) on both sides to avoid a number/string
// mismatch (e.g. 5 === '5' is false) that would otherwise break member
// lookups after this switch.

import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import * as memberService from '../services/memberService'
import { useToast } from './ToastContext'

const MembersContext = createContext(null)

export function MembersProvider({ children }) {
  const [members, setMembers] = useState([])
  const [status, setStatus] = useState('loading') // loading | ready | error
  const { showToast } = useToast()

  const reload = useCallback(async () => {
    setStatus('loading')
    try {
      const data = await memberService.listMembers()
      setMembers(data)
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }, [])

  useEffect(() => {
    reload()
  }, [reload])

  const addMember = useCallback(
    async (input) => {
      const member = await memberService.createMember(input)
      setMembers((current) => [member, ...current])
      showToast(`${member.fullName} added.`)
      return member
    },
    [showToast]
  )

  const editMember = useCallback(
    async (memberId, updates) => {
      const updated = await memberService.updateMember(memberId, updates)
      setMembers((current) => current.map((m) => (String(m.id) === String(memberId) ? updated : m)))
      showToast(`${updated.fullName} updated.`)
      return updated
    },
    [showToast]
  )

  const deactivateMember = useCallback(
    async (memberId) => {
      const updated = await memberService.deactivateMember(memberId)
      setMembers((current) => current.map((m) => (String(m.id) === String(memberId) ? updated : m)))
      showToast(`${updated.fullName} deactivated.`)
      return updated
    },
    [showToast]
  )

  const getMemberById = useCallback(
    (memberId) => members.find((m) => String(m.id) === String(memberId)),
    [members]
  )

  return (
    <MembersContext.Provider
      value={{ members, status, reload, addMember, editMember, deactivateMember, getMemberById }}
    >
      {children}
    </MembersContext.Provider>
  )
}

export function useMembers() {
  const ctx = useContext(MembersContext)
  if (!ctx) throw new Error('useMembers must be used within MembersProvider')
  return ctx
}
