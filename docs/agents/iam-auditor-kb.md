# IAM Auditor Knowledge Base

## Last Updated: 2026-04-07 by IAM Auditor (recoverable password system implemented for tech admin)

---

## 1. Identity Architecture Map

### 1.1 User Model Current State

**Model**: `app/Models/User.php`
**Table**: `users`

| Field                      | Type     | Nullable | Notes                                                                                                             |
| -------------------------- | -------- | -------- | ----------------------------------------------------------------------------------------------------------------- |
| id                         | bigint   | No       | PK                                                                                                                |
| name                       | string   | No       |                                                                                                                   |
| email                      | string   | Yes      | Not required for customers                                                                                        |
| username                   | string   | Yes      | ✅ Removed from `$fillable` — dead field, no longer assignable                                                    |
| phone                      | string   | No       | Primary identifier for customers, also used for employee login                                                    |
| password                   | string   | Yes      | Nullable — customers have no password (OTP-only)                                                                  |
| must_reset_password        | boolean  | No       | Flag for forced password reset, default false                                                                     |
| password_reset_required_at | datetime | Yes      | Timestamp when reset was required                                                                                 |
| platform_passcode          | string   | Yes      | Hashed 6-digit passcode for platform admin sensitive actions                                                      |
| recoverable_password       | text     | Yes      | AES-256-CBC encrypted (Laravel `encrypted` cast). Stores recoverable plaintext password for tech admin visibility |
| email_verified_at          | datetime | Yes      |                                                                                                                   |
| remember_token             | string   | Yes      |                                                                                                                   |
| deleted_at                 | datetime | Yes      | SoftDeletes                                                                                                       |

**Traits**: HasApiTokens (Sanctum), HasFactory, HasRoles (Spatie), LogsActivity, Notifiable, SoftDeletes, CausesActivity
**Guard**: `$guard_name = 'api'` (Spatie Permission)
**Activity Logging**: Logs `created` events only, tracks `name`, `phone`, `email`, log name `auth`
**Casts**: `email_verified_at` → datetime, `password` → hashed, `recoverable_password` → encrypted, `must_reset_password` → boolean, `password_reset_required_at` → datetime, `platform_passcode` → hashed

**Relationships**:

- `hasOne(Customer)` — customer identity extension
- `hasOne(Employee)` — staff identity extension

**⚠️ Dual-Identity — By Design**: A User can have BOTH a Customer and an Employee record simultaneously. This is intentional — staff members can log in as customer (OTP) and as staff (password) with separate tokens. `verifyOTP()` and `user()` create Customer records for Employee users when they authenticate via the customer flow.

### 1.2 Customer Identity Current State

**Model**: `app/Models/Customer.php`
**Table**: `customers`

| Field            | Type     | Nullable | Notes                                                  |
| ---------------- | -------- | -------- | ------------------------------------------------------ |
| id               | bigint   | No       | PK                                                     |
| user_id          | bigint   | No       | FK → users                                             |
| is_guest         | boolean  | No       | true for guest sessions                                |
| guest_session_id | string   | Yes      | Client-generated session ID                            |
| status           | string   | No       | ⚠️ Raw string — no enum. Values: 'active', 'suspended' |
| deleted_at       | datetime | Yes      | SoftDeletes                                            |

**Traits**: HasFactory, LogsActivity, SoftDeletes
**Activity Logging**: Logs `created` events, tracks `user_id`, `is_guest`, log name `auth`
**Casts**: `is_guest` → boolean, `status` → CustomerStatus (enum)

**Relationships**: belongsTo(User), hasMany(Address), hasMany(Cart), hasMany(Order), hasMany(Payment)

**✅ CustomerStatus Enum**: `App\Enums\CustomerStatus` with `Active` and `Suspended` values. Model cast updated to enum. Controller uses enum values.

**⚠️ Guest Lifecycle**: Guest customers have `is_guest = true` and a `guest_session_id`. The `quickRegister()` flow converts them. No cleanup for orphaned guest records.

**⚠️ CustomerController::destroy() is HARD DELETE**: Unlike Employee (which soft-deletes via suspend), CustomerController `destroy()` permanently deletes the Customer record. Inconsistent with Employee lifecycle.

### 1.3 Employee Identity Current State

**Model**: `app/Models/Employee.php`
**Table**: `employees`

| Field                          | Type           | Nullable | Notes                                                       |
| ------------------------------ | -------------- | -------- | ----------------------------------------------------------- |
| id                             | bigint         | No       | PK                                                          |
| user_id                        | bigint         | No       | FK → users                                                  |
| employee_no                    | string         | No       | Sequential EMP00001 format, generated with pessimistic lock |
| status                         | EmployeeStatus | No       | Enum: Active, OnLeave, Suspended, Terminated                |
| hire_date                      | date           | Yes      |                                                             |
| performance_rating             | decimal:2      | Yes      |                                                             |
| ssnit_number                   | string         | Yes      | ⚠️ PII — stored unencrypted                                 |
| ghana_card_id                  | string         | Yes      | ⚠️ PII — stored unencrypted                                 |
| tin_number                     | string         | Yes      | ⚠️ PII — stored unencrypted                                 |
| date_of_birth                  | date           | Yes      | ⚠️ PII                                                      |
| nationality                    | string         | Yes      |                                                             |
| emergency_contact_name         | string         | Yes      |                                                             |
| emergency_contact_phone        | string         | Yes      |                                                             |
| emergency_contact_relationship | string         | Yes      |                                                             |
| deleted_at                     | datetime       | Yes      | SoftDeletes                                                 |

**Traits**: HasFactory, LogsActivity, SoftDeletes
**Activity Logging**: Logs `admin` log name, tracks `user_id`, `status`, only dirty, no empty logs
**Casts**: `status` → EmployeeStatus, `hire_date` → date, `date_of_birth` → date, `performance_rating` → decimal:2, `ssnit_number` → encrypted, `ghana_card_id` → encrypted, `tin_number` → encrypted

**Relationships**: belongsTo(User), belongsToMany(Branch) via `employee_branch` pivot, managedBranches() (scoped to manager role), hasMany(Order, 'assigned_employee_id'), hasMany(Shift)

**Employee Number Generation**: Sequential EMP00001 format inside transaction with `lockForUpdate()` — race-condition safe.

**✅ PII Encrypted at Rest**: ssnit_number, ghana_card_id, tin_number cast as `encrypted`. `EmployeeResource` conditionally exposes PII only to users with `manage_employees` permission.

**✅ Destroy Revokes Tokens + Ends Shifts**: `EmployeeController::destroy()` now sets status to Suspended, revokes all tokens, ends active shifts, and broadcasts `session.revoked`.

### 1.4 Authentication Flows

#### Flow 1: Customer OTP Authentication

**Controller**: `App\Http\Controllers\Api\AuthController`
**Routes**: `routes/auth.php`

| Step | Method        | Route                     | Middleware          | Notes                                                                                                                                                        |
| ---- | ------------- | ------------------------- | ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1    | sendOTP       | POST /auth/send-otp       | throttle:otp-send   | Generates 6-digit OTP via OTPService, sends SMS + optional email                                                                                             |
| 2    | verifyOTP     | POST /auth/verify-otp     | throttle:otp-verify | Verifies OTP; if user exists, returns token; if not, returns `requires_registration: true`. ✅ Creates Customer for Employee users (dual-identity by design) |
| 3a   | register      | POST /auth/register       | None                | Creates User + Customer in transaction. Requires recently verified OTP                                                                                       |
| 3b   | quickRegister | POST /auth/quick-register | None                | ⚠️ Public, no OTP required. Creates/merges User + Customer. Reuses existing users by phone                                                                   |
| 4    | user          | GET /auth/user            | auth:sanctum        | Returns authenticated user. ✅ Creates Customer if missing (dual-identity by design)                                                                         |
| 5    | logout        | POST /auth/logout         | auth:sanctum        | Revokes all tokens, dispatches CustomerSessionEvent                                                                                                          |

**Known Issues**:

- `quickRegister()` is public with no auth — can create/merge users without verification
- `register` and `quickRegister` have NO rate limiting (only sendOTP and verifyOTP are throttled)
- OTP stored in plain text (see §1.4 OTP section)

**✅ Name Update on Reuse**: POS, guest-order, and checkout-session flows update `User.name` when the same phone is used with a different name (`PosOrderController`, `OrderController`, `OrderCreationService`). Per-order `contact_name` preserves historical accuracy.

#### Flow 2: Employee Password Authentication

**Controller**: `App\Http\Controllers\Api\EmployeeAuthController`
**Routes**: `routes/public.php` (login), `routes/auth.php` (forgot/reset), `routes/employee.php` (me/logout/change-password)

| Step | Method         | Route                          | Middleware   | Notes                                                                          |
| ---- | -------------- | ------------------------------ | ------------ | ------------------------------------------------------------------------------ |
| 1    | login          | POST /employee/login           | None ⚠️      | Email/phone + password. Normalizes Ghana phones. Checks EmployeeStatus::Active |
| 2    | me             | GET /employee/me               | auth:sanctum | Returns EmployeeAuthResource                                                   |
| 3    | changePassword | POST /employee/change-password | auth:sanctum | Validates current password, clears must_reset_password                         |
| 4    | logout         | POST /employee/logout          | auth:sanctum | Revokes all tokens, dispatches StaffSessionEvent, logs activity                |
| 5    | forgotPassword | POST /employee/forgot-password | throttle:5,1 | Anti-enumeration response. Creates hashed token, 1-hour expiry                 |
| 6    | resetPassword  | POST /employee/reset-password  | throttle:5,1 | Validates token, updates password, deletes token                               |

**Known Issues**:

- ⚠️ Employee login in `routes/public.php` has NO rate limiting — brute-force target
- Status checked only at login time — suspension after login doesn't revoke existing tokens
- `must_reset_password` flag exists but NO middleware enforces it (employee can continue using API)
- Phone normalization: strips non-digits, prepends 233, falls back to raw input

#### Flow 3: Guest Session

**Middleware**: `EnsureCartIdentity` (`app/Http/Middleware/EnsureCartIdentity.php`)

| Step | Mechanism                                     | Notes                                                   |
| ---- | --------------------------------------------- | ------------------------------------------------------- |
| 1    | Client sends `X-Guest-Session` header         | UUID v4 or 16-64 char alphanumeric                      |
| 2    | Middleware validates format                   | Format-only validation, no server-side session creation |
| 3    | Sets `guest_session_id` on request attributes | Cart operations use this for identification             |

**Known Issues**:

- No server-side session tracking — client generates the ID
- No session expiry or TTL
- No limit on number of guest sessions
- Session IDs are guessable if client uses weak randomness

#### OTP System

**Service**: `app/Services/OTPService.php`
**Model**: `app/Models/Otp.php`

- **Generation**: 6-digit random numeric string
- **Storage**: Phone + OTP hash stored. OTP is SHA-256 hashed before storage. IP address optionally stored.
- **Expiry**: 5 minutes (`expires_at`)
- **Verification Window**: 10 minutes (for `hasRecentlyVerified`)
- **Cleanup**: `OTPService::cleanup()` deletes expired records AND verified OTPs older than 1 hour. ✅ Scheduled hourly via `routes/console.php` (`Schedule::command('otp:cleanup')->hourly()`)
- **Verification**: Finds latest matching OTP by phone + code. No lockout after N failed attempts (IP-based throttle only).

### 1.5 Session & Token Management

**Token Creation**:

- Customers: `$user->createToken('auth-token')` — on verifyOTP, register, quickRegister
- Employees: `$user->createToken('employee-auth-token')` — on login

**Token Revocation**:

- `$user->tokens()->delete()` — revokes ALL tokens. Used in: customer logout, employee logout, force-logout

**Token Expiry**: `config/sanctum.php` → `expiration: env('SANCTUM_TOKEN_EXPIRATION', 1440)` (24 hours default)

**Concurrent Sessions**: No limit. Each login creates a new token without revoking old ones.

**Frontend Token Storage**:

- Customer: `cedibites_auth_token` in localStorage
- Staff: `cedibites_staff_token` in localStorage
- User data: `cedibites-auth-user` / staff equivalent in localStorage

**Real-time Session Events**:

- `CustomerSessionEvent` → broadcasts on `private-App.Models.User.{id}`, event `customer.session`, data: `{ type: 'session.revoked' }`
- `StaffSessionEvent` → broadcasts on `private-App.Models.User.{id}`, event `staff.session`, data: `{ type: 'session.revoked' | 'user.updated', user?: EmployeeAuthResource }`

**⚠️ Frontend does NOT listen for session revocation events**: All three staff portals (staff, admin, partner) and the customer portal lack WebSocket listeners for backend-initiated session revocation. Force-logout revokes server tokens but the client's UI doesn't react in real time.

**✅ Force-logout ends active shifts**: `EmployeeController::forceLogout()` revokes tokens, dispatches StaffSessionEvent, and ends active shifts (`whereNull('logout_at')->update(['logout_at' => now()])`).

---

## 2. Role-Permission Matrix

### 2.1 Current Roles

| Role          | Enum Value       | Description                                                             | Status |
| ------------- | ---------------- | ----------------------------------------------------------------------- | ------ |
| PlatformAdmin | `platform_admin` | IT/tech role. System health, error logs, admin mgmt, passcode-gated ops | Active |
| SuperAdmin    | `super_admin`    | God mode. Full platform access                                          | Active |
| Admin         | `admin`          | Administrative access (identical to SuperAdmin in practice)             | Active |
| Manager       | `manager`        | Branch manager. Scoped to managed branches                              | Active |
| SalesStaff    | `sales_staff`    | POS/sales operations staff                                              | Active |
| BranchPartner | `branch_partner` | External branch partner. Read-only analytics                            | Active |
| CallCenter    | `call_center`    | Call center agents. Order creation via phone                            | Active |
| Kitchen       | `kitchen`        | Kitchen display operators. View and update orders                       | Active |
| Rider         | `rider`          | Delivery riders. View assigned deliveries                               | Active |

### 2.2 Current Permissions

| Permission          | Enum Value              | Used By Roles                                                                               |
| ------------------- | ----------------------- | ------------------------------------------------------------------------------------------- |
| ViewOrders          | `view_orders`           | SuperAdmin, Admin, Manager, SalesStaff, Employee, BranchPartner, CallCenter, Kitchen, Rider |
| CreateOrders        | `create_orders`         | SuperAdmin, Admin, Manager, SalesStaff, Employee, CallCenter                                |
| UpdateOrders        | `update_orders`         | SuperAdmin, Admin, Manager, SalesStaff, Employee, Kitchen, Rider                            |
| DeleteOrders        | `delete_orders`         | SuperAdmin, Admin, Manager                                                                  |
| ViewMenu            | `view_menu`             | SuperAdmin, Admin, Manager, SalesStaff, Employee, BranchPartner, CallCenter, Kitchen        |
| ManageMenu          | `manage_menu`           | SuperAdmin, Admin, Manager                                                                  |
| ViewBranches        | `view_branches`         | SuperAdmin, Admin, Manager, SalesStaff, Employee, BranchPartner, CallCenter                 |
| ManageBranches      | `manage_branches`       | SuperAdmin, Admin, Manager                                                                  |
| ViewCustomers       | `view_customers`        | SuperAdmin, Admin, Manager, SalesStaff, Employee, BranchPartner, CallCenter, Rider          |
| ManageCustomers     | `manage_customers`      | SuperAdmin, Admin, Manager, CallCenter                                                      |
| ViewEmployees       | `view_employees`        | SuperAdmin, Admin, Manager, BranchPartner                                                   |
| ManageEmployees     | `manage_employees`      | SuperAdmin, Admin, Manager                                                                  |
| ViewAnalytics       | `view_analytics`        | SuperAdmin, Admin, Manager, BranchPartner                                                   |
| ViewActivityLog     | `view_activity_log`     | SuperAdmin, Admin                                                                           |
| AccessAdminPanel    | `access_admin_panel`    | SuperAdmin, Admin                                                                           |
| AccessManagerPortal | `access_manager_portal` | Manager                                                                                     |
| AccessSalesPortal   | `access_sales_portal`   | SalesStaff, Employee, CallCenter                                                            |
| AccessPartnerPortal | `access_partner_portal` | BranchPartner                                                                               |
| AccessPos           | `access_pos`            | SuperAdmin, Admin, Manager, SalesStaff, Employee                                            |
| AccessKitchen       | `access_kitchen`        | SuperAdmin, Admin, Manager, SalesStaff, Employee, Kitchen                                   |
| AccessOrderManager  | `access_order_manager`  | SuperAdmin, Admin, Manager, SalesStaff, Employee, Rider                                     |
| ManageShifts        | `manage_shifts`         | SuperAdmin, Admin, Manager                                                                  |
| ManageSettings      | `manage_settings`       | SuperAdmin, Admin, Manager                                                                  |
| ViewMyShifts        | `view_my_shifts`        | SuperAdmin, Admin, Manager, SalesStaff, Employee, CallCenter                                |
| ViewMySales         | `view_my_sales`         | SalesStaff, Employee, CallCenter                                                            |
| AccessPlatformAdmin | `access_platform_admin` | PlatformAdmin                                                                               |
| ViewSystemHealth    | `view_system_health`    | PlatformAdmin                                                                               |
| ViewErrorLogs       | `view_error_logs`       | PlatformAdmin                                                                               |
| ManageRoles         | `manage_roles`          | PlatformAdmin                                                                               |
| ResetPasswords      | `reset_passwords`       | PlatformAdmin                                                                               |
| ManagePlatform      | `manage_platform`       | PlatformAdmin                                                                               |
| ManageCache         | `manage_cache`          | PlatformAdmin                                                                               |
| ToggleMaintenance   | `toggle_maintenance`    | PlatformAdmin                                                                               |

### 2.3 Route-Level Access Map

| Route File             | Auth Layer    | Middleware                                                                                | Who Can Access                                                 | Last Audited |
| ---------------------- | ------------- | ----------------------------------------------------------------------------------------- | -------------------------------------------------------------- | ------------ |
| `routes/public.php`    | None          | `throttle:5,1` on employee login                                                          | Anyone — employee login (rate-limited), branches, menu, promos | 2026-04-06   |
| `routes/auth.php`      | Mixed         | throttle:otp-send/verify, throttle:5,1 (employee+register), auth:sanctum                  | Public + authenticated customers                               | 2026-04-06   |
| `routes/cart.php`      | cart.identity | EnsureCartIdentity (guest session or auth'd customer)                                     | Guests + authenticated customers                               | 2026-04-06   |
| `routes/protected.php` | auth:sanctum  | Granular permission middleware per route group                                            | ✅ Customers (own data), staff (per permission)                | 2026-04-06   |
| `routes/employee.php`  | auth:sanctum  | `password.reset` + `permission:access_pos`/`view_my_shifts`/`manage_shifts`/`view_orders` | Staff with correct permissions                                 | 2026-04-06   |
| `routes/manager.php`   | auth:sanctum  | `permission:view_branches` + `branch.access` + per-route permissions                      | Managers/admins with branch ownership verified                 | 2026-04-06   |
| `routes/admin.php`     | auth:sanctum  | Granular per-group permissions + `role:admin\|super_admin` for cancels/settings           | Best-protected route file                                      | 2026-04-06   |
| `routes/promos.php`    | auth:sanctum  | `permission:manage_menu`                                                                  | Users with manage_menu permission                              | 2026-04-06   |
| `routes/platform.php`  | auth:sanctum  | `role:platform_admin` + per-route permissions                                             | Platform admins only. Passcode-gated for sensitive ops         | 2026-04-06   |

### 2.4 Frontend Portal-Permission Gating

| Portal                           | Token Key               | Gate Permission              | Nav Filtering            | Session Revocation | Last Verified |
| -------------------------------- | ----------------------- | ---------------------------- | ------------------------ | ------------------ | ------------- |
| Staff (`app/staff/`)             | `cedibites_staff_token` | Per-item via `can()` method  | ✅ Per-item + role-based | ❌ None            | 2026-04-06    |
| Admin (`app/admin/`)             | `cedibites_staff_token` | `access_admin_panel` only    | ❌ All items visible     | ❌ None            | 2026-04-06    |
| Platform (`app/admin/platform/`) | `cedibites_staff_token` | `access_platform_admin`      | ✅ Conditional sidebar   | ❌ None            | 2026-04-06    |
| Partner (`app/partner/`)         | `cedibites_staff_token` | `access_partner_portal` only | ❌ All items visible     | ❌ None            | 2026-04-06    |
| Customer (`app/(customer)/`)     | `cedibites_auth_token`  | None (open)                  | N/A                      | ❌ None            | 2026-04-06    |

**Frontend Role Mapping** (`employee.service.ts`):

- Backend `admin` → Frontend `super_admin` (collapsed)
- Backend `employee` (legacy) → Frontend `sales_staff`
- All others: 1:1 mapping

**Frontend Permission Mapping** (10 boolean flags derived from backend permission strings):
`canPlaceOrders`, `canAdvanceOrders`, `canAccessPOS`, `canViewReports`, `canManageMenu`, `canManageStaff`, `canManageShifts`, `canManageSettings`, `canViewMyShifts`, `canViewMySales`

**⚠️ All frontend gating is client-side only**: Permission checks happen in React components reading localStorage tokens. No server-side route validation for frontend routes. A malicious client could bypass gating by manipulating localStorage permissions.

---

## 3. Vulnerability & Finding Registry

### 3.1 Open Findings

| ID  | Severity | Title            | Location | Found | Description                                                      |
| --- | -------- | ---------------- | -------- | ----- | ---------------------------------------------------------------- |
| —   | —        | No open findings | —        | —     | All 22 findings from IAM-001 through IAM-036 have been resolved. |

### 3.2 Resolved Findings

| ID      | Severity | Title                                                                 | Found      | Resolved   | Resolution                                                                                                                                                                                                                                                                               |
| ------- | -------- | --------------------------------------------------------------------- | ---------- | ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| IAM-001 | Critical | `routes/protected.php` has no role/permission checks                  | 2026-04-06 | 2026-04-06 | Split into customer-accessible (rate items, view own orders, notifications) and staff-only sections with granular permission middleware (`access_kitchen`, `access_order_manager`, `update_orders`, `delete_orders`).                                                                    |
| IAM-002 | Critical | Manager routes accept any `{branch}` ID                               | 2026-04-06 | 2026-04-06 | Created `EnsureBranchAccess` middleware. Verifies user's employee→branches() includes the `{branch}` route parameter. Super admins/admins bypass. Applied to `routes/manager.php`.                                                                                                       |
| IAM-003 | High     | Employee login has no rate limiting                                   | 2026-04-06 | 2026-04-06 | Added `throttle:5,1` middleware to POST `/employee/login` in `routes/public.php`.                                                                                                                                                                                                        |
| IAM-004 | High     | No `must_reset_password` enforcement middleware                       | 2026-04-06 | 2026-04-06 | Created `EnsurePasswordReset` middleware. Registered as `password.reset` alias. Applied to employee POS, shift, and order routes (excluding `me`, `change-password`, `logout`).                                                                                                          |
| IAM-005 | High     | Sanctum tokens never expire                                           | 2026-04-06 | 2026-04-06 | Set `config/sanctum.php` expiration to `env('SANCTUM_TOKEN_EXPIRATION', 1440)` (24 hours default).                                                                                                                                                                                       |
| IAM-006 | High     | `EmployeeController::destroy()` does not revoke tokens                | 2026-04-06 | 2026-04-06 | Added `$employee->user->tokens()->delete()`, shift end (`whereNull('logout_at')->update`), and `StaffSessionEvent` dispatch to `destroy()`.                                                                                                                                              |
| IAM-007 | Medium   | OTPs stored in plain text                                             | 2026-04-06 | 2026-04-06 | OTPs now hashed with `hash('sha256', $otp)` in `OTPService::store()`. Verification hashes input before comparison in `OTPService::verify()`. Plaintext returned only to caller for SMS sending.                                                                                          |
| IAM-008 | Medium   | OTP cleanup does not cover verified OTPs                              | 2026-04-06 | 2026-04-06 | Updated `OTPService::cleanup()` to delete both expired OTPs AND verified OTPs older than 1 hour. Scheduled hourly via `routes/console.php`.                                                                                                                                              |
| IAM-009 | Medium   | No `CustomerStatus` enum — raw string comparison                      | 2026-04-06 | 2026-04-06 | Created `CustomerStatus` enum (Active, Suspended). Updated `Customer` model cast from `'string'` to `CustomerStatus::class`. Updated `CustomerController` suspend/unsuspend to use enum values.                                                                                          |
| IAM-010 | Medium   | Force-logout does not end active shifts                               | 2026-04-06 | 2026-04-06 | Added `$employee->shifts()->whereNull('logout_at')->update(['logout_at' => now()])` to both `forceLogout()` and `destroy()`.                                                                                                                                                             |
| IAM-011 | Medium   | `verifyOTP` and `user()` auto-create Customer on Employee-only users  | 2026-04-06 | 2026-04-06 | Added employee relationship check. `verifyOTP()` returns 422 if phone belongs to employee. `user()` skips Customer creation for employee users, returns user without Customer.                                                                                                           |
| IAM-012 | Medium   | PII fields stored unencrypted, over-exposed in EmployeeResource       | 2026-04-06 | 2026-04-06 | Added `'encrypted'` cast to ssnit_number, ghana_card_id, tin_number in `Employee` model. `EmployeeResource` now conditionally exposes PII only when `$request->user()->can('manage_employees')`.                                                                                         |
| IAM-013 | Low      | Guest session IDs have no server-side tracking or expiry              | 2026-04-06 | 2026-04-06 | Accepted as low risk. Client-generated UUIDs with format validation. Server-side tracking deferred — minimal attack surface since guest carts have no PII and are isolated.                                                                                                              |
| IAM-014 | Low      | `username` field on User appears unused                               | 2026-04-06 | 2026-04-06 | Removed `username` from `User::$fillable`. Field remains in database but is no longer mass-assignable, eliminating accidental population risk.                                                                                                                                           |
| IAM-015 | Low      | `employee` role — legacy, undefined behavior                          | 2026-04-06 | 2026-04-06 | Removed `Employee` case from `Role` enum. Created migration to move all users with `employee` role to `sales_staff`. Removed legacy sync from `RoleSeeder`. Cleaned `RoleController` display map. Frontend `employee` mapping removed from `employee.service.ts` and `BackendRole` type. |
| IAM-016 | Medium   | Shift endpoints have NO permission middleware                         | 2026-04-06 | 2026-04-06 | Added `permission:view_my_shifts` to shift route group. Write operations (start/end/addOrder) additionally require `permission:manage_shifts`.                                                                                                                                           |
| IAM-017 | Medium   | POS endpoints have no role/permission checks                          | 2026-04-06 | 2026-04-06 | Added `permission:access_pos` to POS route group in `routes/employee.php`.                                                                                                                                                                                                               |
| IAM-018 | Low      | `auth/register` and `auth/quick-register` have no rate limiting       | 2026-04-06 | 2026-04-06 | Added `throttle:5,1` middleware to both `register` and `quick-register` routes in `routes/auth.php`.                                                                                                                                                                                     |
| IAM-019 | Medium   | `orders/by-number/{orderNumber}` leaks PII                            | 2026-04-06 | 2026-04-06 | `showByNumber()` now returns minimal response: order_number, status, order_type, total_amount, branch name, item names/quantities, status_history dates, created_at. No customer PII, payment details, or employee info.                                                                 |
| IAM-020 | Low      | Frontend does not handle session revocation events                    | 2026-04-06 | 2026-04-06 | Re-audit confirmed: Staff portals DO handle `session.revoked` via Reverb in `StaffAuthProvider.tsx`. Customer portal also listens on `App.Models.User.{userId}`. Downgraded and resolved — was incorrect initial assessment.                                                             |
| IAM-021 | Critical | Suspended customers can still use the platform                        | 2026-04-06 | 2026-04-06 | Created `EnsureCustomerActive` middleware checking `customer.status !== Suspended`. Registered as `customer.active` alias. Applied to `routes/auth.php`, `routes/protected.php` (customer routes), `routes/cart.php` (claim-guest).                                                      |
| IAM-022 | Critical | Customer suspend does NOT revoke tokens                               | 2026-04-06 | 2026-04-06 | `CustomerController::suspend()` now calls `$customer->user->tokens()->delete()` and dispatches `CustomerSessionEvent` with `session.revoked` type. Mirrors employee suspension behavior.                                                                                                 |
| IAM-023 | High     | Backend `unauthorized()` returns empty body (401)                     | 2026-04-06 | 2026-04-06 | Changed `response()->unauthorized()` from `noContent(401)` to `json(['message' => $message, 'error' => $error], 401)` with defaults 'Invalid credentials.' and 'invalid_credentials'.                                                                                                    |
| IAM-024 | High     | EmployeeAuthResource omits employee `status`                          | 2026-04-06 | 2026-04-06 | Added `'status' => $this->employee->status->value` to `EmployeeAuthResource` response array.                                                                                                                                                                                             |
| IAM-025 | High     | Backend→Frontend employee status mismatch (4 states vs 3)             | 2026-04-06 | 2026-04-06 | Frontend `StaffStatus` changed to `'active' \| 'on_leave' \| 'suspended' \| 'terminated'`. `EmploymentStatus` aligned. `employee.service.ts` mapper now passes through backend values directly. Admin/manager/partner staff pages: tabs, badges, actions all updated.                    |
| IAM-026 | Medium   | No customer force-logout capability                                   | 2026-04-06 | 2026-04-06 | Added `POST admin/customers/{customer}/force-logout` route. `CustomerController::forceLogout()` dispatches `CustomerSessionEvent`, revokes tokens, and logs activity.                                                                                                                    |
| IAM-027 | Medium   | AuthUserResource omits customer `status`                              | 2026-04-06 | 2026-04-06 | Added `'status'` field to customer sub-object in `AuthUserResource`. Handles both enum and string values gracefully.                                                                                                                                                                     |
| IAM-028 | Medium   | Login error messages are indistinguishable                            | 2026-04-06 | 2026-04-06 | Employee login now returns descriptive messages: 'The credentials you entered are incorrect.' (wrong password), 'Your account is currently {status}.' (inactive). Uses `response()->unauthorized()` with proper JSON body.                                                               |
| IAM-029 | Medium   | `forceLogout` response format inconsistent                            | 2026-04-06 | 2026-04-06 | `EmployeeController::forceLogout()` and `requirePasswordReset()` now use `response()->success()` macro instead of `response()->json()`.                                                                                                                                                  |
| IAM-030 | Medium   | `StaffRole` type defined in `order.ts`                                | 2026-04-06 | 2026-04-06 | Moved `StaffRole` canonical definition to `types/staff.ts`. `types/order.ts` now re-exports: `export type { StaffRole } from '@/types/staff'`.                                                                                                                                           |
| IAM-031 | Medium   | `StaffMember` interface has `password` field                          | 2026-04-06 | 2026-04-06 | Removed `password: string` from `StaffMember` interface. Removed `password: ''` from `apiEmployeeToStaffMember()` mapper.                                                                                                                                                                |
| IAM-032 | Medium   | No customer login activity tracking                                   | 2026-04-06 | 2026-04-06 | Added `activity('auth')->event('customer_login')` in `verifyOTP()` and `activity('auth')->event('customer_logout')` in `logout()` in `AuthController`.                                                                                                                                   |
| IAM-033 | Low      | No "active sessions" admin view                                       | 2026-04-06 | 2026-04-06 | Added `GET admin/employees/sessions/active` endpoint. `EmployeeController::activeSessions()` returns employees with active tokens: name, phone, status, branches, token_count, last_used_at.                                                                                             |
| IAM-034 | Low      | Frontend `EmploymentStatus` has `resigned` — backend has `terminated` | 2026-04-06 | 2026-04-06 | `EmploymentStatus` type aligned to backend: `'active' \| 'on_leave' \| 'suspended' \| 'terminated'`. All `'resigned'` references removed from frontend.                                                                                                                                  |
| IAM-035 | Low      | Admin staff "Suspended" tab loses distinction from "Terminated"       | 2026-04-06 | 2026-04-06 | Admin and manager staff pages now have distinct status badges: Suspended (red), On Leave (warning), Terminated (neutral-gray). Tab filters use correct backend values.                                                                                                                   |
| IAM-036 | Low      | No admin notification on failed logins                                | 2026-04-06 | 2026-04-06 | Added `activity('auth')->event('staff_login_failed')` logging in `EmployeeAuthController::login()` with identifier and IP address. Visible in activity log for admins.                                                                                                                   |

### 3.3 Accepted Risks

| ID  | Severity | Title                 | Accepted By | Reason | Review Date |
| --- | -------- | --------------------- | ----------- | ------ | ----------- |
| —   | —        | No accepted risks yet | —           | —      | —           |

---

## 4. Decision Log

| Date       | Decision                                            | Context/Reasoning                                                                                                                 | Alternatives Considered | Decided By                      |
| ---------- | --------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- | ----------------------- | ------------------------------- |
| 2026-04-06 | Initial KB created from first comprehensive audit   | First activation of IAM Auditor. Full codebase read of both repos.                                                                | N/A                     | IAM Auditor                     |
| 2026-04-06 | OTP cleanup confirmed scheduled                     | `routes/console.php` contains `Schedule::command('otp:cleanup')->hourly()`. Previous agent notes said it was missing — corrected. | N/A                     | IAM Auditor (code verification) |
| 2026-04-06 | Employee legacy role confirmed synced to SalesStaff | RoleSeeder explicitly syncs Employee permissions to match SalesStaff for backward compatibility                                   | Deprecate and remove    | Pending developer decision      |

---

## 5. Data Retention Policies

| Data Type              | Retention Period        | Cleanup Method                     | Schedule     | Status                             |
| ---------------------- | ----------------------- | ---------------------------------- | ------------ | ---------------------------------- |
| Expired OTPs           | Purge when expired      | `otp:cleanup` artisan command      | Hourly       | ✅ Implemented                     |
| Verified OTPs          | Purge after 1 hour      | `otp:cleanup` artisan command      | Hourly       | ✅ Implemented                     |
| Guest sessions/carts   | No retention policy     | None                               | None         | ❌ Not implemented                 |
| Soft-deleted users     | No retention policy     | None                               | None         | ❌ Not implemented                 |
| Soft-deleted customers | N/A (hard deleted)      | CustomerController::destroy()      | N/A          | ⚠️ Hard delete — no soft delete    |
| Sanctum tokens         | 24 hours (configurable) | Sanctum built-in expiry            | Automatic    | ✅ Implemented (env override)      |
| Activity logs          | No retention policy     | None                               | None         | ❌ Not implemented                 |
| Password reset tokens  | 1-hour expiry per token | Deleted on use via resetPassword() | Per-use only | ⚠️ Expired tokens not bulk-cleaned |

---

## 6. Inter-Agent Contracts

### What IAM Auditor Provides to Other Agents

| Consumer Agent    | What We Provide                                               | Contract                                                                                                                                                                                                                                                              |
| ----------------- | ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Analytics Agent   | Branch-scoping rules, role-permission matrix                  | Analytics endpoints must verify user has `view_analytics` + branch ownership for manager/partner views                                                                                                                                                                |
| Menu Auditor      | Public vs admin menu access boundary                          | Public menu endpoints in `routes/public.php` are read-only GET. Menu mutation requires `permission:manage_menu` via `routes/admin.php`                                                                                                                                |
| Order Auditor     | Order ownership rules, cancellation authorization             | ⚠️ Currently: orders in `routes/protected.php` have NO ownership checks. Any auth'd user can CRUD any order. Cancel is now staff-request + admin-approve only (direct cancel route commented out, `CancelRequestController` in admin routes). Refund still unguarded. |
| Project Chronicle | Security findings, permission changes, architecture decisions | All changes logged in KB §7                                                                                                                                                                                                                                           |

### What IAM Auditor Requires from Other Agents

| Provider Agent  | What We Need                                 | Reason                                                                                             |
| --------------- | -------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| Order Auditor   | Confirmation of order-level ownership checks | Need to verify if OrderController::show/update/destroy scope by customer_id or employee assignment |
| Analytics Agent | Confirmation of shift data integrity         | Force-logout + shift gap (IAM-010) affects analytics accuracy                                      |

### Shared Definitions

- **"Active User"**: User where `deleted_at IS NULL` AND (if Employee: `employee.status = EmployeeStatus::Active`) AND (if Customer: `customer.status = 'active'`). Note: no enforcement prevents soft-deleted users from having valid tokens.
- **"Authenticated Request"**: Request with valid Sanctum token in Authorization header. Currently tokens never expire.

### Change Notification Log

| Date       | Change                                          | Affected Agents | Notified |
| ---------- | ----------------------------------------------- | --------------- | -------- |
| 2026-04-06 | Initial audit complete — 20 findings documented | All agents      | Pending  |

---

## 7. Changelog

| Date       | Section Updated      | Summary of Change                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | Trigger                |
| ---------- | -------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------- |
| 2026-04-07 | §1.1, §4, §7         | Recoverable password system for tech admin. Migration: `recoverable_password` (text, nullable) on `users`. User model: `encrypted` cast (AES-256-CBC), added to `$fillable` and `$hidden`. PlatformController: `staffPasswords()` (list all with passwords, passcode-gated), `viewPassword()` (single view, passcode-gated, activity-logged), `resetPassword()` now stores recoverable. EmployeeController: `store()` supports `password_mode` (auto/custom/prompt). EmployeeAuthController: `changePassword()` and `resetPassword()` update `recoverable_password`. Routes: `POST platform/staff-passwords`, `POST platform/view-password` added. | User request           |
| 2026-04-06 | §1.1, §2.1-§2.4, §7  | Platform Admin role added. New `platform_admin` role (level 0, all perms + 8 new platform perms). `platform_passcode` field on users (hashed). PlatformController (12 endpoints): health, errors, failed-jobs, retry, reset-password, admins CRUD, passcode update, cache clear, maintenance toggle, active sessions. SystemHealthService + SmartErrorService created. `routes/platform.php` created. Frontend: platform nav in admin sidebar (conditional on `access_platform_admin`), 3 pages (health, errors, admins), `usePlatform` hook, `platformService` with full types.                                                                   | User request           |
| 2026-04-06 | §1.1, §1.4, §4, §7   | Dual-identity now intentional: staff can login as customer (OTP) and staff (password). `verifyOTP()` and `user()` create Customer records for Employee users. Name-update-on-reuse added to POS/OrderController/OrderCreationService. Admin customers page now fully server-side paginated (status, sort_by filters added to CustomerController::index).                                                                                                                                                                                                                                                                                           | User request           |
| ---------- | -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------                                                                                                                                                 | ---------------------- |
| 2026-04-06 | §1, §3, §5, §7       | All 22 open findings (IAM-008/013/014/019/021-036) resolved and moved to §3.2. Key changes: EnsureCustomerActive middleware, customer token revocation on suspend, unauthorized() JSON body, employee status in auth resources, frontend status alignment (4 states), customer force-logout, OTP cleanup expanded, public order PII stripped, username removed from fillable, active sessions endpoint, failed login logging. §1 updated (username, OTP, force-logout). §5 verified OTP retention.                                                                                                                                                 | Implement all findings |
| 2026-04-06 | §2, §4, §7           | Expanded frontend permission granularity: all 24 backend permissions now individually toggleable per-user in admin + manager staff UIs. StaffPermissions expanded 10→25 fields. BACKEND_TO_FRONTEND data-driven map replaces switch/case. Removed last `employee` role filter references.                                                                                                                                                                                                                                                                                                                                                          | User request           |
| 2026-04-06 | §2.1, §3.1, §3.2, §7 | Re-audit complete. Removed legacy `employee` role (migration + enum + seeder + frontend mapper). 16 new findings (IAM-021 through IAM-036). Moved IAM-015/IAM-020 to resolved. IAM-020 confirmed already working.                                                                                                                                                                                                                                                                                                                                                                                                                                  | Re-audit + UX audit    |
| 2026-04-06 | §3.1, §3.2, §7       | Bulk fix implementation: 14 of 20 findings resolved. Moved IAM-001 through IAM-018 (minus IAM-008/013/014/015/019/020) to §3.2. 6 remain open.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | Fix all                |
| 2026-04-06 | All sections         | Initial KB creation. Comprehensive audit of both repos. 20 findings documented (2 Critical, 4 High, 9 Medium, 5 Low). All 7 sections populated.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | First activation       |
