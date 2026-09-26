# Database Schema — `asian_gym`

This documents every table in the `asian_gym` MySQL database, what it stores,
and why it exists. Tables are grouped by whether they were **created/renamed
in the members/payments migration session** or **already existed** in the
backend beforehand. A couple of things have changed since that original
pass — most notably `members` gained a set of extended profile fields, and
the whole `refunds` feature (table, model, controller, UI) was removed
entirely — both reflected below.

---

## Created / renamed this session

### `payments`
Renamed from `transactions` (model `Transaction` → `Payment`) at your request,
so the DB/API naming matches "payment" domain terminology used elsewhere in
the app, instead of the more generic "transaction".

Stores every one-time and recurring payment a member makes (membership fees,
guest passes, add-ons, etc.) — this is the "Transactions" list under Payments
in the UI.

| Column | Type | Why |
|---|---|---|
| `id` | bigint, PK | Auto-increment id (replaces the old mock's string ids like `txn_3001`) |
| `member_id` | bigint, FK → `members.id` | Which member this payment belongs to |
| `amount` | decimal(10,2) | Payment amount |
| `method` | varchar | `Cash \| Card \| Bank Transfer \| Online` |
| `type` | varchar | `OneTime \| Recurring` |
| `status` | varchar, default `Paid` | `Paid \| Pending \| Failed \| Refunded \| PartiallyRefunded` — the last two remain valid values a payment's status can be set to, but nothing in the app sets them automatically now that the refund-issuing feature (below) is gone |
| `date` | date | Date the payment was made |
| `due_date` | date, nullable | For `Pending` payments — when it's due |
| `invoice_number` | varchar, unique | Auto-generated (`INV-####`) server-side |
| `notes` | text, nullable | Free-text note |

---

## Already existed (migrated into `asian_gym` unchanged)

### `members`
The core gym-member roster — the "Members" section of the app. This is one
of the two tables you originally asked me to create; it turned out the
backend already had it fully built (migrations/model/controller) mirroring
the frontend's mock member data, so no schema work was needed here, just
pointing the frontend at it and seeding it into the new database.

A later session extended the member form with a fuller profile (ID card
number, NIC, body metrics, occupation, login credentials for a future member
portal, etc.) — those columns are nullable at the DB level even though
several are required by the frontend form, since the original 8 seeded
members predate them and a `NOT NULL` column with no sensible default would
have broken those rows.

| Column | Type | Why |
|---|---|---|
| `id` | bigint, PK | |
| `branch_id` | bigint, FK → `branches.id`, nullable | Which branch this member is registered at (not yet exposed in the frontend UI — left as an unused nullable column per your earlier decision) |
| `member_id_number` | varchar, nullable | External ID/badge/card number, e.g. from a legacy paper system — free text, no uniqueness constraint |
| `full_name`, `phone` | varchar | Contact info |
| `nic` | varchar, nullable | National ID card number |
| `email` | varchar, nullable, unique | Optional — phone/WhatsApp are the required contact channels |
| `whatsapp_number` | varchar, nullable | Required by the form even though the column is nullable (see note above) |
| `dob` | date, nullable | Date of birth |
| `gender` | varchar, nullable | `Female \| Male \| Other` |
| `address` | varchar, nullable | |
| `weight_kg`, `height_cm` | decimal(5,2), nullable | Stored canonically in kg/cm; the form's unit toggle (kg/lb, cm/ft+in) converts on entry/display only |
| `occupation` | varchar, nullable | |
| `emergency_contact_name`, `emergency_contact_phone` | varchar, nullable | |
| `username` | varchar, nullable, unique | Preferred login username — collected for a future member portal (no member-facing login exists yet) |
| `password` | varchar, nullable | Bcrypt-hashed (Eloquent `hashed` cast), hidden from all API responses. Only ever set at member creation — editing a member never touches it, same pattern as staff `users.password` |
| `join_date` | date | |
| `status` | varchar, default `Active` | `Active \| Inactive \| Suspended` — members are soft-deleted by flipping this to `Inactive` (never hard-deleted, so their payment history stays intact) |
| `notes` | text, nullable | |

### `attendance`
Daily check-in/check-out records shown on the Members → Attendance page.
Already existed with a full controller; the frontend was still reading a
static array instead of calling it, which I fixed this session.

| Column | Type | Why |
|---|---|---|
| `id` | bigint, PK | |
| `member_id` | bigint, FK → `members.id` | |
| `member_name`, `email` | varchar | Denormalized copies of the member's name/email, so the attendance list doesn't need a join for its own display |
| `date` | date | |
| `check_in_time`, `check_out_time` | varchar, nullable | Stored as display strings (e.g. `"06:30 AM"`), matching the original mock format |
| `status` | varchar, default `Present` | `Present \| Late \| Absent` |

### `roles`
Holds the permission sets assignable to staff accounts — this is the
"separate table for role data" you asked about. Each role is a named bundle
of permission keys (`dashboard.view`, `members.edit`, `setup.manage`, etc.)
plus an `is_admin` bypass flag. Managed from Setup → Roles.

| Column | Type | Why |
|---|---|---|
| `id` | bigint, PK | |
| `name` | varchar(50), unique | e.g. "Administrator", "Front Desk" |
| `description` | text, nullable | |
| `permissions` | json, nullable | Array of permission-key strings this role grants |
| `is_admin` | boolean, default false | Bypasses every permission check entirely |
| `is_system` | boolean, default false | Marks built-in roles (Administrator, Front Desk) that can't be deleted |

### `users`
Staff login accounts (not gym members — these are the people who log into
this admin app). Each user has one role.

| Column | Type | Why |
|---|---|---|
| `id` | bigint, PK | |
| `full_name`, `email` (unique) | varchar | |
| `password` | varchar | Hashed |
| `role_id` | bigint, FK → `roles.id`, nullable | Which permission set this user has |
| `branch_id` | bigint, FK → `branches.id`, nullable | Which branch this staff member is assigned to |
| `status` | varchar, default `Active` | |

### `branches`
Supports multi-location gyms (currently just one seeded "Main Branch").
Referenced by `members.branch_id` and `users.branch_id`.

| Column | Type | Why |
|---|---|---|
| `id` | bigint, PK | |
| `name` | varchar, unique | |
| `address`, `phone` | varchar, nullable | |
| `is_default` | boolean, default false | Which branch new records fall back to when no branch is picked |

### `settings`
Simple key/value store for gym-wide settings (e.g. gym name, email config)
edited under Setup → Gym Settings / Email Setup.

| Column | Type | Why |
|---|---|---|
| `id` | bigint, PK | |
| `key` | varchar, unique | Setting name |
| `value` | text, nullable | Setting value |

### `login_activities`
Audit trail of staff sign-ins, shown under Setup → Login Activity.

| Column | Type | Why |
|---|---|---|
| `id` | bigint, PK | |
| `user_id` | bigint, FK → `users.id` | Who logged in |
| `ip_address`, `user_agent` | varchar/text, nullable | Where/what they logged in from |
| `logged_in_at` | timestamp | When |

### Laravel framework tables
Not app data — these are Laravel's own infrastructure, created automatically
by its default migrations:

- **`sessions`** — server-side session storage (`SESSION_DRIVER=database`)
- **`cache`**, **`cache_locks`** — app cache backend (`CACHE_STORE=database`)
- **`jobs`**, **`job_batches`**, **`failed_jobs`** — queued background jobs (`QUEUE_CONNECTION=database`)
- **`personal_access_tokens`** — Laravel Sanctum's API auth tokens (this is what makes the `Bearer <token>` login work)
- **`migrations`** — Laravel's own record of which migration files have run

---

## Relationships at a glance

```
branches ──< members ──< payments
    │            │
    │            └──< attendance
    │
    └──< users >── roles
              │
              └──< login_activities
```
