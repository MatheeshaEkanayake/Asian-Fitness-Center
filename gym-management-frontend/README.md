# Ironline — Gym Management Frontend

A React + Vite frontend covering **Member Management** and **Payment Management**
for gym staff/admin use. Built from the accompanying flow/navigation spec.

## Stack
- React 19 + React Router 7
- Vite 8
- Tailwind CSS v4 (CSS-first config via `@theme` in `src/index.css` — no `tailwind.config.js` needed)
- No external state library: React Context + hooks (`src/context/`)
- No backend yet: a mock/local data layer stands in for a real API (`src/services/`, `src/data/`)

## Requirements
- Node.js **>= 18**
- npm 9+ (ships with Node 18)

## Getting started
```bash
npm install
npm run dev       # start the dev server
npm run build      # production build → dist/
npm run preview    # preview the production build
npm run lint        # oxlint
```

## Troubleshooting
**`SyntaxError: The requested module 'node:util' does not provide an export named 'styleText'`**
This means something in `node_modules` was resolved to a package version that
needs Node 20.19+/22.12+ (Vite 8 and `@vitejs/plugin-react` 6+ both require
this). This project's `package.json` is pinned to versions that support
Node 18+ (Vite 6, `@vitejs/plugin-react` 4, `react-router-dom` 6) — if you hit
this error, delete `node_modules` and `package-lock.json` and run
`npm install` again to make sure nothing newer got pulled in. Upgrading to
Node 20 or 22 LTS also works and is recommended long-term, since Node 18
reached end-of-life in April 2025.

## Project structure
```
src/
  pages/
    DashboardPage.jsx
    members/            MemberListPage, MemberFormPage, MemberDetailPage
    payments/            PaymentsDashboardPage, Transactions*, Subscriptions*
  components/
    shared/               Table, Modal, Button, FormField, StatusBadge, etc.
    payments/             RefundModal, ReceiptView
  layout/
    AppLayout.jsx         Sidebar + topbar shell, wraps all routes
  context/
    MembersContext.jsx    Member state + CRUD actions
    PaymentsContext.jsx   Transactions + subscriptions state + actions
    ToastContext.jsx      App-wide toast notifications
  services/
    memberService.js      Mock async "API" for members
    paymentService.js     Mock async "API" for transactions/refunds
    subscriptionService.js Mock async "API" for subscriptions
  data/
    mockData.js            Seed data
  utils/
    format.js, id.js
```

## Routes
| Route | Screen |
|---|---|
| `/` | Dashboard |
| `/members` | Member list |
| `/members/new` | Add member |
| `/members/:memberId` | Member detail (Profile / Payment history tabs) |
| `/members/:memberId/edit` | Edit member |
| `/payments` | Payments dashboard (summary) |
| `/payments/transactions` | Transactions list (`?memberId=` to filter) |
| `/payments/transactions/new` | Record payment (`?memberId=` to prefill) |
| `/payments/transactions/:transactionId` | Transaction detail (refund, mark paid, receipt) |
| `/payments/subscriptions` | Subscriptions list |
| `/payments/subscriptions/new` | Create subscription |
| `/payments/subscriptions/:subscriptionId` | Subscription detail (pause/resume/cancel, billing history) |
| `/payments/subscriptions/:subscriptionId/edit` | Edit subscription |

## Connecting a real backend
Every page and component talks only to the functions in `src/services/*.js` —
never directly to `src/data/mockData.js`. To swap in a real API:

1. Replace the body of each function in `memberService.js`, `paymentService.js`,
   and `subscriptionService.js` with real `fetch`/HTTP-client calls.
2. Keep each function's signature and return shape the same, and the context
   providers, pages, and components need no changes.
3. Delete `src/services/db.js` and `src/data/mockData.js` once nothing imports them.

## Design notes
- Palette, typography (Archivo for display, IBM Plex Sans for body/data) and
  spacing are defined as CSS custom properties in `src/index.css`'s `@theme` block —
  change a token there and it updates everywhere.
- Members are **soft-deleted** (status → `Inactive`) rather than removed, so
  linked payment history stays intact.
- Refunds are capped at the original paid amount and mark the transaction
  `Refunded` or `PartiallyRefunded`.
- "Email receipt" is a stubbed action (shows a toast) — wire it up to a real
  email endpoint when one exists.
