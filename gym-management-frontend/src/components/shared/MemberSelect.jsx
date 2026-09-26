import { useMemo } from 'react'
import { useMembers } from '../../context/MembersContext'
import Combobox from './Combobox'

// Member picker for any form: browse every member, or type part of a name
// to shortlist. `onChange` gets the member id as a string (like a <select>).
export default function MemberSelect({ id, value, onChange, error, disabled }) {
  const { members } = useMembers()

  const options = useMemo(
    () => members.map((m) => ({ value: String(m.id), label: m.fullName })),
    [members]
  )

  return (
    <Combobox
      id={id}
      value={value}
      onChange={onChange}
      options={options}
      placeholder="Search or select a member…"
      emptyMessage="No members match that name"
      error={error}
      disabled={disabled}
    />
  )
}
