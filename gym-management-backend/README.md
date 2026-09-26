# Gym Management — Laravel Backend

REST API backend for the **Asian Fitness Gym Management System**.  
Mirrors all business logic that was previously handled client-side in the React frontend (`gym-management-frontend`).

---

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Language | PHP 8.2+ |
| Database | SQLite (local) / MySQL (production) |
| API style | JSON REST |

---

## What this backend covers

| Frontend module | Backend equivalent |
|---|---|
| `src/services/memberService.js` | `MemberController` + `Member` model |
| `src/services/paymentService.js` | `TransactionController`, `RefundController`, `PaymentSummaryController` |
| `src/data/mockData.js` | Database seeders (MemberSeeder, TransactionSeeder, RefundSeeder, AttendanceSeeder) |
| `src/utils/id.js` (generateId) | Auto-increment primary keys / Laravel UUID |
| `src/context/MembersContext.jsx` | `GET/POST/PUT /api/members` |
| `src/context/PaymentsContext.jsx` | `GET/POST/PUT /api/transactions`, `GET /api/payments/summary` |
| `src/pages/members/MemberAttendencePage.jsx` | `GET /api/attendance` |

---

## Quick Start

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# 3. Create the SQLite database file
touch database/database.sqlite

# 4. Run migrations and seed with mock data
php artisan migrate --seed

# 5. Start the development server
php artisan serve
# → API available at http://localhost:8000/api
```

---

## API Endpoints

### Members — `/api/members`

| Method | Endpoint | Description | Frontend equivalent |
|---|---|---|---|
| GET | `/api/members` | List all members (with search, filter, sort query params) | `listMembers()` |
| POST | `/api/members` | Create a new member | `createMember(input)` |
| GET | `/api/members/{id}` | Get a single member | `getMember(memberId)` |
| PUT | `/api/members/{id}` | Update member profile | `updateMember(memberId, updates)` |
| PATCH | `/api/members/{id}/status` | Change member status | `setMemberStatus(memberId, status)` |
| DELETE | `/api/members/{id}` | Soft-deactivate a member (sets status → Inactive) | `deactivateMember(memberId)` |
| GET | `/api/members/{id}/transactions` | List transactions for a member | `listTransactionsForMember(memberId)` |

### Transactions — `/api/transactions`

| Method | Endpoint | Description | Frontend equivalent |
|---|---|---|---|
| GET | `/api/transactions` | List all transactions (sorted by date desc) | `listTransactions()` |
| POST | `/api/transactions` | Record a new payment | `recordPayment(input)` |
| GET | `/api/transactions/{id}` | Get a single transaction | `getTransaction(transactionId)` |
| PUT | `/api/transactions/{id}/mark-paid` | Mark a pending transaction as paid | `markTransactionPaid(transactionId)` |

### Refunds — `/api/transactions/{id}/refunds`

| Method | Endpoint | Description | Frontend equivalent |
|---|---|---|---|
| POST | `/api/transactions/{id}/refunds` | Issue a refund for a transaction | `issueRefund(transactionId, {amount, reason})` |
| GET | `/api/transactions/{id}/refunds` | List refunds for a transaction | `listRefundsForTransaction(transactionId)` |

### Payments Summary

| Method | Endpoint | Description | Frontend equivalent |
|---|---|---|---|
| GET | `/api/payments/summary` | Revenue this month, pending/overdue/failed counts, refunds issued | `getPaymentSummary()` |

### Attendance — `/api/attendance`

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/attendance` | List all attendance records |
| POST | `/api/attendance` | Record a check-in / check-out |

---

## Project Structure

```
gym-management-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php
│   │   │   └── Api/
│   │   │       ├── MemberController.php
│   │   │       ├── TransactionController.php
│   │   │       ├── RefundController.php
│   │   │       ├── PaymentSummaryController.php
│   │   │       └── AttendanceController.php
│   │   └── Requests/
│   │       ├── StoreMemberRequest.php
│   │       ├── UpdateMemberRequest.php
│   │       ├── StoreTransactionRequest.php
│   │       └── IssueRefundRequest.php
│   └── Models/
│       ├── Member.php
│       ├── Transaction.php
│       ├── Refund.php
│       └── Attendance.php
├── bootstrap/
│   └── app.php
├── config/
│   ├── cors.php
│   └── database.php
├── database/
│   ├── migrations/
│   │   ├── 2026_09_09_000001_create_members_table.php
│   │   ├── 2026_09_09_000002_create_transactions_table.php
│   │   ├── 2026_09_09_000003_create_refunds_table.php
│   │   └── 2026_09_09_000004_create_attendance_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── MemberSeeder.php
│       ├── TransactionSeeder.php
│       ├── RefundSeeder.php
│       └── AttendanceSeeder.php
├── routes/
│   └── api.php
├── .env.example
├── composer.json
└── README.md
```

---

## CORS

The backend allows requests from `FRONTEND_URL` (default: `http://localhost:5173`) — the Vite dev server.  
Configure this in `.env` before connecting the frontend.

---

## Switching to MySQL

In `.env`, set:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gym_management
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Then create the database and re-run migrations:

```bash
php artisan migrate:fresh --seed
```
