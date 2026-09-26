import { useEffect, useId, useRef, useState } from 'react'
import { FiCheck, FiChevronDown } from 'react-icons/fi'
import { fieldClasses } from './FormField'

/**
 * Searchable dropdown: click to browse the whole list like a <select>, or
 * type to shortlist it. Arrow keys move, Enter picks, Escape closes.
 *
 * `options` is [{ value, label }]; `onChange` gets the picked value (not
 * an event). Leaving the field without picking keeps the current value.
 *
 * `freeText` turns it into a text box with suggestions: `value` is the text
 * itself, typing calls `onChange(text)`, picking a suggestion calls
 * `onChange(option.label)`, and anything not in the list is still allowed.
 * `onSelect(option)` fires whenever a suggestion is picked (either mode).
 */
export default function Combobox({
  id,
  value,
  onChange,
  onSelect,
  options,
  freeText = false,
  placeholder = 'Select…',
  emptyMessage = 'No matches',
  error,
  disabled,
}) {
  const listId = useId()
  const wrapperRef = useRef(null)
  const inputRef = useRef(null)
  const listRef = useRef(null)
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [activeIndex, setActiveIndex] = useState(0)

  const selected = freeText ? null : options.find((o) => String(o.value) === String(value))
  const text = freeText ? value || '' : query
  const needle = text.trim().toLowerCase()
  const filtered = needle ? options.filter((o) => o.label.toLowerCase().includes(needle)) : options

  const openList = () => {
    if (disabled || open) return
    setQuery('')
    // Free text: nothing highlighted until the user arrows down, so Enter
    // doesn't swap what they typed for a suggestion.
    const selectedIndex = freeText ? -1 : options.findIndex((o) => String(o.value) === String(value))
    setActiveIndex(freeText ? -1 : Math.max(selectedIndex, 0))
    setOpen(true)
  }

  const close = () => {
    setOpen(false)
    setQuery('')
  }

  const pick = (option) => {
    onChange(freeText ? option.label : option.value)
    onSelect?.(option)
    close()
  }

  // Close when clicking anywhere outside the field.
  useEffect(() => {
    if (!open) return
    const onPointerDown = (e) => {
      if (!wrapperRef.current?.contains(e.target)) close()
    }
    document.addEventListener('pointerdown', onPointerDown)
    return () => document.removeEventListener('pointerdown', onPointerDown)
  }, [open])

  // Keep the highlighted option in view while arrowing through a long list.
  useEffect(() => {
    if (open) listRef.current?.children[activeIndex]?.scrollIntoView({ block: 'nearest' })
  }, [open, activeIndex])

  const handleKeyDown = (e) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      if (!open) return openList()
      const step = e.key === 'ArrowDown' ? 1 : -1
      setActiveIndex((i) => Math.min(Math.max(i + step, 0), filtered.length - 1))
    } else if (e.key === 'Enter' && open) {
      if (filtered[activeIndex]) {
        e.preventDefault() // don't submit the form
        pick(filtered[activeIndex])
      } else if (freeText) {
        close() // keep the typed text; Enter submits as in a normal field
      } else {
        e.preventDefault()
      }
    } else if (e.key === 'Escape' && open) {
      e.stopPropagation()
      close()
    } else if (e.key === 'Tab') {
      close()
    }
  }

  return (
    <div ref={wrapperRef} className="relative">
      <input
        ref={inputRef}
        id={id}
        type="text"
        role="combobox"
        aria-expanded={open}
        aria-controls={listId}
        aria-autocomplete="list"
        aria-activedescendant={open && filtered[activeIndex] ? `${listId}-${activeIndex}` : undefined}
        autoComplete="off"
        disabled={disabled}
        // Closed: show the picked option. Open: show what's being typed,
        // with the picked option as a hint.
        value={freeText ? text : open ? query : selected?.label || ''}
        placeholder={!freeText && open && selected ? selected.label : placeholder}
        onFocus={openList}
        onClick={openList}
        onChange={(e) => {
          if (freeText) onChange(e.target.value)
          else setQuery(e.target.value)
          setActiveIndex(freeText ? -1 : 0)
          setOpen(true)
        }}
        onKeyDown={handleKeyDown}
        className={`${fieldClasses} pr-9 placeholder:text-[color:var(--color-ink-faint)] ${
          error ? 'border-[color:var(--color-danger)]' : 'border-[color:var(--color-line-strong)]'
        }`}
      />
      <button
        type="button"
        tabIndex={-1}
        aria-label={open ? 'Close list' : 'Open list'}
        disabled={disabled}
        onClick={() => {
          if (open) close()
          else inputRef.current?.focus()
        }}
        className="absolute inset-y-0 right-0 grid w-9 place-items-center text-[color:var(--color-ink-faint)]"
      >
        <FiChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {/* Free text hides an empty list — a name that isn't listed is fine. */}
      {open && !(freeText && filtered.length === 0) && (
        <ul
          ref={listRef}
          id={listId}
          role="listbox"
          className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-[color:var(--color-line)] bg-[color:var(--color-surface)] py-1 text-sm shadow-lg"
        >
          {filtered.length === 0 ? (
            <li className="px-3 py-2 text-[color:var(--color-ink-faint)]">{emptyMessage}</li>
          ) : (
            filtered.map((option, i) => {
              const isSelected = freeText ? option.label === value : String(option.value) === String(value)
              return (
                <li
                  key={option.value}
                  id={`${listId}-${i}`}
                  role="option"
                  aria-selected={isSelected}
                  // pointerdown + preventDefault keeps focus in the input.
                  onPointerDown={(e) => e.preventDefault()}
                  onClick={() => pick(option)}
                  onMouseEnter={() => setActiveIndex(i)}
                  className={`flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-[color:var(--color-ink)] ${
                    i === activeIndex ? 'bg-[color:var(--color-paper)]' : ''
                  } ${isSelected ? 'font-medium' : ''}`}
                >
                  <span className="truncate">{option.label}</span>
                  {isSelected && <FiCheck className="h-4 w-4 shrink-0 text-[color:var(--color-brand)]" />}
                </li>
              )
            })
          )}
        </ul>
      )}
    </div>
  )
}
