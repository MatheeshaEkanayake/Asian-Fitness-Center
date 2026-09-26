import { createContext, useCallback, useContext, useRef, useState } from 'react'

const ToastContext = createContext(null)

let idCounter = 0

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])
  const timers = useRef({})

  const dismiss = useCallback((id) => {
    setToasts((current) => current.filter((t) => t.id !== id))
    clearTimeout(timers.current[id])
    delete timers.current[id]
  }, [])

  const showToast = useCallback(
    (message, { tone = 'success' } = {}) => {
      idCounter += 1
      const id = idCounter
      setToasts((current) => [...current, { id, message, tone }])
      timers.current[id] = setTimeout(() => dismiss(id), 4000)
    },
    [dismiss]
  )

  return (
    <ToastContext.Provider value={{ showToast }}>
      {children}
      <div className="fixed bottom-5 right-5 z-50 flex flex-col gap-2 w-80">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            role="status"
            className={`flex items-start justify-between gap-3 rounded-md border px-4 py-3 shadow-sm text-sm font-body ${
              toast.tone === 'error'
                ? 'bg-[color:var(--color-danger-soft)] border-[color:var(--color-danger)]/30 text-[color:var(--color-danger)]'
                : 'bg-[color:var(--color-brand-soft)] border-[color:var(--color-brand)]/30 text-[color:var(--color-brand-dark)]'
            }`}
          >
            <span>{toast.message}</span>
            <button
              onClick={() => dismiss(toast.id)}
              aria-label="Dismiss notification"
              className="opacity-60 hover:opacity-100"
            >
              ✕
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used within ToastProvider')
  return ctx
}
