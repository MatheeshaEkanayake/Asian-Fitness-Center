// Who a payment belongs to, even once that member is gone from the member
// list. In order: the live member (MembersContext), the archived member the
// payments API still sends (`member`, has `deletedAt`), or — after the
// 6-month purge — the copy saved on the payment itself.
//
// Returns the member plus `removed`: null (current), 'archived' or 'purged'.
export function paymentMember(transaction, getMemberById) {
  const live = transaction.memberId != null ? getMemberById(transaction.memberId) : null
  if (live) return { ...live, removed: null }

  if (transaction.member) {
    return { ...transaction.member, removed: transaction.member.deletedAt ? 'archived' : null }
  }

  if (transaction.memberName) {
    return {
      id: null,
      fullName: transaction.memberName,
      memberIdNumber: transaction.memberIdNumber,
      phone: transaction.memberPhone,
      removed: 'purged',
    }
  }

  return null
}
