# IAM Auditor Knowledge Base

## Last Updated: 2026-04-06 01:40 by IAM Auditor (initial creation)

---

## 1. Identity Architecture Map

### 1.1 User Model Current State

**Model**: `app/Models/User.php`
**Table**: `users`

| Field | Type | Nullable | Notes |
|-------|------|----------|-------|
| id | bigint | No | PK |
| name | string | No | |
| email | string | Yes | Not required for customers |
| username | string | Yes | ⚠️ In `$fillable` but unused in any auth flow — dead field |
| phone | string | No | Primary identifier for customers, also used for employee login |
| password | string | Yes | Nullable — customers have no password (OTP-only) |
| must_reset_password | boolean | No | Flag for forced password reset, default false |
| password_reset_required_at | datetime | Yes | Timestamp when reset was required |
| email_verified_at | datetime | Yes | |
| remember_token | string | Yes | |
| deleted_at | datetime | Yes | SoftDeletes |

**Traits**: HasApiTokens (Sanctum), HasFactory, HasRoles (Spatie), LogsActivity, Notifiable, SoftDeletes, CausesActivity
**Guard**: `$guard_name = 'api'` (Spatie Permission)
**Activity Logging**: Logs `created` events only, tracks `name`, `phone`, `email`, log name `auth`
**Casts**: `email_verified_at` → datetime, `password` → hashed, `must_reset_password` → boolean, `password_reset_required_at` → datetime

**Relationships**:
- `hasOne(Customer)` — customer identity extension
- `hasOne(Employee)` — staff identity extension

**⚠️ Dual-Identity Risk**: A User can have BOTH a Customer and an Employee record simultaneously. No database constraint or application-level guard prevents this. If a staff member verifies an OTP or hits `/auth/user`, they auto-get a Customer record (see §1.4).

### 1.2 Customer Identity Current State

**Model**: `app/Models/Customer.php`
**Table**: `customers`

| Field | Type | Nullable | Notes |
|-------|------|----------|-------|
| id | bigint | No | PK |
| user_id | bigint | No | FK → users |
| is_guest | boolean | No | true for guest sessions |
| guest_session_id | string | Yes | Client-generated session ID |
| status | string | No | ⚠️ Raw string — no enum. Values: 'active', 'suspended' |
| deleted_at | datetime | Yes | SoftDeletes |

**Traits**: HasFactory, LogsActivity, SoftDeletes
**Activity Logging**: Logs `created` events, tracks `user_id`, `is_guest`, log name `auth`
**Casts**: `is_guest` → boolean, `status` → string

**Relationships**: belongsTo(User), hasMany(Address), hasMany(Cart), hasMany(Order), hasMany(Payment)

**⚠️ No CustomerStatus Enum**: Status is a raw string. `CustomerController::suspend()` sets `'suspended'`, `unsuspend()` sets `'active'`. No validation, no enum, no constant. Any typo is silent data corruption.

**⚠️ Guest Lifecycle**: Guest customers have `is_guest = true` and a `guest_session_id`. The `quickRegister()` flow converts them. No cleanup for orphaned guest records.

**⚠️ CustomerController::destroy() is HARD DELETE**: Unlike Employee (which soft-deletes via suspend), CustomerController `destroy()` permanently deletes the Customer record. Inconsistent with Employee lifecycle.

### 1.3 Employee Identity Current State

**Model**: `app/Models/Employee.php`
**Table**: `employees`

| Field | Type | Nullable | Notes |
|-------|------|----------|-------|
| id | bigint | No | PK |
| user_id | bigint | No | FK → users |
| employee_no | string | No | Sequential EMP00001 format, generated with pessimistic lock |
| status | EmployeeStatus | No | Enum: Active, OnLeave, Suspended, Terminated |
| hire_date | date | Yes | |
| performance_rating | decimal:2 | Yes | |
| ssnit_number | string | Yes | ⚠️ PII — stored unencrypted |
| ghana_card_id | string | Yes | ⚠️ PII — stored unencrypted |
| tin_number | string | Yes | ⚠️ PII — stored unencrypted |
| date_of_birth | date | Yes | ⚠️ PII |
| nationality | string | Yes | |
| emergency_contact_name | string | Yes | |
| emergency_contact_phone | string | Yes | |
| emergency_contact_relationship | string | Yes | |
| deleted_at | datetime | Yes | SoftDeletes |

**Traits**: HasFactory, LogsActivity, SoftDeletes
**Activity Logging**: Logs `admin` log name, tracks `user_id`, `status`, only dirty, no empty logs
**Casts**: `status` → EmployeeStatus, `hire_date` → date, `date_of_birth` → date, `performance_rating` → decimal:2

**Relationships**: belongsTo(User), belongsToMany(Branch) via `employee_branch` pivot, managedBranches() (scoped to manager role), hasMany(Order, 'assigned_employee_id'), hasMany(Shift)

**Employee Number Generation**: Sequential EMP00001 format inside transaction with `lockForUpdate()` — race-condition safe.

**⚠️ PII Exposure**: `EmployeeResource` exposes ALL PII fields (SSNIT, Ghana Card, TIN, DOB, emergency contacts) to ANY user with `view_employees` permission. No role-based data minimization.

**⚠️ Destroy = Suspend Only**: `EmployeeController::destroy()` sets status to `Suspended` but does NOT: (a) soft-delete the Employee record, (b) revoke Sanctum tokens, (c) end active shifts. The "destroyed" employee can still authenticate.

### 1.4 Authentication Flows

#### Flow 1: Customer OTP Authentication

**Controller**: `App\Http\Controllers\Api\AuthController`
**Routes**: `routes/auth.php`

| Step | Method | Route | Middleware | Notes |
|------|--------|-------|-----------|-------|
| 1 | sendOTP | POST /auth/send-otp | throttle:otp-send | Generates 6-digit OTP via OTPService, sends SMS + optional email |
| 2 | verifyOTP | POST /auth/verify-otp | throttle:otp-verify | Verifies OTP; if user exists, returns token; if not, returns `requires_registration: true`. ⚠️ Auto-creates Customer on Employee-only users |
| 3a | register | POST /auth/register | None | Creates User + Customer in transaction. Requires recently verified OTP |
| 3b | quickRegister | POST /auth/quick-register | None | ⚠️ Public, no OTP required. Creates/merges User + Customer. Reuses existing users by phone |
| 4 | user | GET /auth/user | auth:sanctum | Returns authenticated user. ⚠️ Auto-creates Customer if missing |
| 5 | logout | POST /auth/logout | auth:sanctum | Revokes all tokens, dispatches CustomerSessionEvent |

**Known Issues**:
- `quickRegister()` is public with no auth — can create/merge users without verification
- `verifyOTP()` and `user()` auto-create Customer records on Employee-only users without explicit intent
- `register` and `quickRegister` have NO rate limiting (only sendOTP and verifyOTP are throttled)
- OTP stored in plain text (see §1.4 OTP section)

#### Flow 2: Employee Password Authentication

**Controller**: `App\Http\Controllers\Api\EmployeeAuthController`
**Routes**: `routes/public.php` (login), `routes/auth.php` (forgot/reset), `routes/employee.php` (me/logout/change-password)

| Step | Method | Route | Middleware | Notes |
|------|--------|-------|-----------|-------|
| 1 | login | POST /employee/login | None ⚠️ | Email/phone + password. Normalizes Ghana phones. Checks EmployeeStatus::Active |
| 2 | me | GET /employee/me | auth:sanctum | Returns EmployeeAuthResource |
| 3 | changePassword | POST /employee/change-password | auth:sanctum | Validates current password, clears must_reset_password |
| 4 | logout | POST /employee/logout | auth:sanctum | Revokes all tokens, dispatches StaffSessionEvent, logs activity |
| 5 | forgotPassword | POST /employee/forgot-password | throttle:5,1 | Anti-enumeration response. Creates hashed token, 1-hour expiry |
| 6 | resetPassword | POST /employee/reset-password | throttle:5,1 | Validates token, updates password, deletes token |

**Known Issues**:
- ⚠️ Employee login in `routes/public.php` has NO rate limiting — brute-force target
- Status checked only at login time — suspension after login doesn't revoke existing tokens
- `must_reset_password` flag exists but NO middleware enforces it (employee can continue using API)
- Phone normalization: strips non-digits, prepends 233, falls back to raw input

#### Flow 3: Guest Session

**Middleware**: `EnsureCartIdentity` (`app/Http/Middleware/EnsureCartIdentity.php`)

| Step | Mechanism | Notes |
|------|-----------|-------|
| 1 | Client sends `X-Guest-Session` header | UUID v4 or 16-64 char alphanumeric |
| 2 | Middleware validates format | Format-only validation, no server-side session creation |
| 3 | Sets `guest_session_id` on request attributes | Cart operations use this for identification |

**Known Issues**:
- No server-side session tracking — client generates the ID
- No session expiry or TTL
- No limit on number of guest sessions
- Session IDs are guessable if client uses weak randomness

#### OTP System

**Service**: `app/Services/OTPService.php`
**Model**: `app/Models/Otp.php`

- **Generation**: 6-digit random numeric string
- **Storage**: Phone + OTP stored in PLAIN TEXT. IP address optionally stored.
- **Expiry**: 5 minutes (`expires_at`)
- **Verification Window**: 10 minutes (for `hasRecentlyVerified`)
- **Cleanup**: `OTPService::cleanup()` deletes expired records. ⚠️ NOW scheduled hourly via `routes/console.php` (`Schedule::command('otp:cleanup')->hourly()`)
- **Verification**: Finds latest matching OTP by phone + code. No lockout after N failed attempts (IP-based throttle only).

### 1.5 Session & Token Management

**Token Creation**:
- Customers: `$user->createToken('auth-token')` — on verifyOTP, register, quickRegister
- Employees: `$user->createToken('employee-auth-token')` — on login

**Token Revocation**:
- `$user->tokens()->delete()` — revokes ALL tokens. Used in: customer logout, employee logout, force-logout

**Token Expiry**: `config/sanctum.php` → `expiration: null` (tokens NEVER expire)

**Concurrent Sessions**: No limit. Each login creates a new token without revoking old ones.

**Frontend Token Storage**:
- Customer: `cedibites_auth_token` in localStorage
- Staff: `cedibites_staff_token` in localStorage
- User data: `cedibites-auth-user` / staff equivalent in localStorage

**Real-time Session Events**:
- `CustomerSessionEvent` → broadcasts on `private-App.Models.User.{id}`, event `customer.session`, data: `{ type: 'session.revoked' }`
- `StaffSessionEvent` → broadcasts on `private-App.Models.User.{id}`, event `staff.session`, data: `{ type: 'session.revoked' | 'user.updated', user?: EmployeeAuthResource }`

**⚠️ Frontend does NOT listen for session revocation events**: All three staff portals (staff, admin, partner) and the customer portal lack WebSocket listeners for backend-initiated session revocation. Force-logout revokes server tokens but the client's UI doesn't react in real time.

**⚠️ Force-logout does NOT end active shifts**: `EmployeeController::forceLogout()` revokes tokens and dispatches StaffSessionEvent but does not end the employee's active Shift record. The `logout_at` remains null, causing analytics inconsistency.

---

## 2. Role-Permission Matrix

### 2.1 Current Roles

| Role | Enum Value | Description | Status |
|------|-----------|-------------|--------|
| SuperAdmin | `super_admin` | God mode. Full platform access | Active |
| Admin | `admin` | Administrative access (identical to SuperAdmin in practice) | Active |
| Manager | `manager` | Branch manager. Scoped to managed branches (⚠️ not enforced) | Active |
| SalesStaff | `sales_staff` | POS/sales operations staff | Active |
| Employee | `employee` | Legacy compatibility role | ⚠️ Legacy — synced to SalesStaff permissions |
| BranchPartner | `branch_partner` | External branch partner. Read-only analytics | Active |
| CallCenter | `call_center` | Call center agents. Order creation via phone | Active |
| Kitchen | `kitchen` | Kitchen display operators. View and update orders | Active |
| Rider | `rider` | Delivery riders. View assigned deliveries | Active |

### 2.2 Current Permissions

| Permission | Enum Value | Used By Roles |
|-----------|-----------|---------------|
| ViewOrders | `view_orders` | SuperAdmin, Admin, Manager, SalesStaff, Employee, BranchPartner, CallCenter, Kitchen, Rider |
| CreateOrders | `create_orders` | SuperAdmin, Admin, Manager, SalesStaff, Employee, CallCenter |
| UpdateOrders | `update_orders` | SuperAdmin, Admin, Manager, SalesStaff, Employee, Kitchen, Rider |
| DeleteOrders | `delete_orders` | SuperAdmin, Admin, Manager |
| ViewMenu | `view_menu` | SuperAdmin, Admin, Manager, SalesStaff, Employee, BranchPartner, CallCenter, Kitchen |
| ManageMenu | `manage_menu` | SuperAdmin, Admin, Manager |
| ViewBranches | `view_branches` | SuperAdmin, Admin, Manager, SalesStaff, Employee, BranchPartner, CallCenter |
| ManageBranches | `manage_branches` | SuperAdmin, Admin, Manager |
| ViewCustomers | `view_customers` | SuperAdmin, Admin, Manager, SalesStaff, Employee, BranchPartner, CallCenter, Rider |
| ManageCustomers | `manage_customers` | SuperAdmin, Admin, Manager, CallCenter |
| ViewEmployees | `view_employees` | SuperAdmin, Admin, Manager, BranchPartner |
| ManageEmployees | `manage_employees` | SuperAdmin, Admin, Manager |
| ViewAnalytics | `view_analytics` | SuperAdmin, Admin, Manager, BranchPartner |
| ViewActivityLog | `view_activity_log` | SuperAdmin, Admin |
| AccessAdminPanel | `access_admin_panel` | SuperAdmin, Admin |
| AccessManagerPortal | `access_manager_portal` | Manager |
| AccessSalesPortal | `access_sales_portal` | SalesStaff, Employee, CallCenter |
| AccessPartnerPortal | `access_partner_portal` | BranchPartner |
| AccessPos | `access_pos` | SuperAdmin, Admin, Manager, SalesStaff, Employee |
| AccessKitchen | `access_kitchen` | SuperAdmin, Admin, Manager, SalesStaff, Employee, Kitchen |
| AccessOrderManager | `access_order_manager` | SuperAdmin, Admin, Manager, SalesStaff, Employee, Rider |
| ManageShifts | `manage_shifts` | SuperAdmin, Admin, Manager |
| ManageSettings | `manage_settings` | SuperAdmin, Admin, Manager |
| ViewMyShifts | `view_my_shifts` | SuperAdmin, Admin, Manager, SalesStaff, Employee, CallCenter |
| ViewMySales | `view_my_sales` | SalesStaff, Employee, CallCenter |

### 2.3 Route-Level Access Map

| Route File | Auth Layer | Middleware | Who Can Access | Last Audited |
|-----------|-----------|-----------|---------------|-------------|
| `routes/public.php` | None | None | Anyone — employee login, branches, menu, promos | 2026-04-06 |
| `routes/auth.php` | Mixed | throttle:otp-send/verify, throttle:5,1 (employee), auth:sanctum (user/logout) | Public + authenticated customers | 2026-04-06 |
| `routes/cart.php` | cart.identity | EnsureCartIdentity (guest session or auth'd customer) | Guests + authenticated customers | 2026-04-06 |
| `routes/protected.php` | auth:sanctum | **NONE beyond auth** | ⚠️ **ANY authenticated user** (customer, employee, admin) | 2026-04-06 |
| `routes/employee.php` | auth:sanctum | Mixed — employee orders require `permission:view_orders`, shifts have **NONE** | Any authenticated user (shifts unguarded) | 2026-04-06 |
| `routes/manager.php` | auth:sanctum | `permission:view_branches` + per-route permissions | Users with view_branches (⚠️ no branch ownership check) | 2026-04-06 |
| `routes/admin.php` | auth:sanctum | Granular per-group permissions + `role:admin\|super_admin` for cancels/settings | Best-protected route file | 2026-04-06 |
| `routes/promos.php` | auth:sanctum | `permission:manage_menu` | Users with manage_menu permission | 2026-04-06 |

### 2.4 Frontend Portal-Permission Gating

| Portal | Token Key | Gate Permission | Nav Filtering | Session Revocation | Last Verified |
|--------|----------|----------------|--------------|-------------------|--------------|
| Staff (`app/staff/`) | `cedibites_staff_token` | Per-item via `can()` method | ✅ Per-item + role-based | ❌ None | 2026-04-06 |
| Admin (`app/admin/`) | `cedibites_staff_token` | `access_admin_panel` only | ❌ All items visible | ❌ None | 2026-04-06 |
| Partner (`app/partner/`) | `cedibites_staff_token` | `access_partner_portal` only | ❌ All items visible | ❌ None | 2026-04-06 |
| Customer (`app/(customer)/`) | `cedibites_auth_token` | None (open) | N/A | ❌ None | 2026-04-06 |

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

| ID | Severity | Title | Location | Found | Description |
|----|----------|-------|----------|-------|-------------|
| IAM-001 | Critical | `routes/protected.php` has no role/permission checks | `routes/protected.php` | 2026-04-06 | Any authenticated user (including customers) can: view kitchen orders, view order-manager orders, CRUD any order, initiate refunds, rate menu items. Kitchen/order-manager endpoints should require `access_kitchen`/`access_order_manager`. Order CRUD should scope by ownership (customers) or require staff permissions. Refund should require `update_orders`. |
| IAM-002 | Critical | Manager routes accept any `{branch}` ID | `routes/manager.php`, `BranchController` | 2026-04-06 | Manager routes require `permission:view_branches` but do NOT verify the requesting manager owns/manages the branch in the URL. A manager can query stats, employees, orders, revenue for ANY branch by guessing IDs. No `EnsureBranchAccess` middleware exists. |
| IAM-003 | High | Employee login has no rate limiting | `routes/public.php` line: `Route::post('login', ...)` | 2026-04-06 | POST `/employee/login` has no throttle middleware. Brute-force target. Other auth endpoints (forgot/reset password) correctly have `throttle:5,1`. |
| IAM-004 | High | No `must_reset_password` enforcement middleware | Missing middleware | 2026-04-06 | `User.must_reset_password` flag and `password_reset_required_at` exist. `EmployeeAuthController::changePassword()` clears the flag. But NO middleware blocks API access while flag is true. A compromised initial password works indefinitely. |
| IAM-005 | High | Sanctum tokens never expire | `config/sanctum.php` → `expiration: null` | 2026-04-06 | Tokens have no configured expiration. A stolen token is valid forever unless explicitly revoked via logout or force-logout. |
| IAM-006 | High | `EmployeeController::destroy()` does not revoke tokens | `app/Http/Controllers/Api/EmployeeController.php` | 2026-04-06 | `destroy()` sets status to `Suspended` but does NOT (a) revoke Sanctum tokens, (b) soft-delete the Employee record, (c) end active shifts. The "destroyed" employee retains valid tokens. |
| IAM-007 | Medium | OTPs stored in plain text | `app/Services/OTPService.php`, `app/Models/Otp.php` | 2026-04-06 | 6-digit OTP stored as-is in the `otp` column. Should be hashed (SHA-256 with phone as salt, or bcrypt) to reduce breach impact. Verification would hash input and compare. |
| IAM-008 | Medium | OTP cleanup is scheduled but OTP table may still grow | `routes/console.php`, `OTPService::cleanup()` | 2026-04-06 | `otp:cleanup` runs hourly. ✅ Scheduled. However, verified OTPs may not be cleaned (only expired ones). Need to verify `cleanup()` logic covers both expired AND verified records. |
| IAM-009 | Medium | No `CustomerStatus` enum — raw string comparison | `app/Models/Customer.php`, `app/Http/Controllers/Api/CustomerController.php` | 2026-04-06 | Customer status uses raw strings ('active', 'suspended'). No enum, no validation, no constants. Inconsistent with `EmployeeStatus` which is a proper PHP enum. |
| IAM-010 | Medium | Force-logout does not end active shifts | `app/Http/Controllers/Api/EmployeeController.php::forceLogout()` | 2026-04-06 | Force-logout revokes tokens and broadcasts session.revoked but does NOT end the employee's active Shift (logout_at remains null). Analytics will compute shift duration incorrectly. |
| IAM-011 | Medium | `verifyOTP` and `user()` auto-create Customer on Employee-only users | `app/Http/Controllers/Api/AuthController.php` | 2026-04-06 | If an employee verifies an OTP or hits `/auth/user`, the system auto-creates a Customer record for them. This creates dual-identity (User → Customer + Employee) without explicit consent. |
| IAM-012 | Medium | PII fields stored unencrypted, over-exposed in EmployeeResource | `app/Models/Employee.php`, `app/Http/Resources/EmployeeResource.php` | 2026-04-06 | SSNIT number, Ghana Card ID, TIN number, date_of_birth stored as plain strings. `EmployeeResource` exposes ALL of them to any user with `view_employees` permission (BranchPartner, Manager, Admin). No data minimization based on viewer role. |
| IAM-013 | Low | Guest session IDs have no server-side tracking or expiry | `app/Http/Middleware/EnsureCartIdentity.php` | 2026-04-06 | Guest session IDs are client-generated, format-validated only. No server-side session creation, no TTL, no cleanup. Guessable if client uses weak randomness. |
| IAM-014 | Low | `username` field on User appears unused | `app/Models/User.php` | 2026-04-06 | `username` is in `$fillable` but not used in any authentication flow, controller, or form request. Dead field risk — could cause confusion. |
| IAM-015 | Low | `employee` role marked "legacy compatibility" | `app/Enums/Role.php` | 2026-04-06 | The `Employee` enum case is marked as legacy. In `RoleSeeder`, its permissions are synced to match `SalesStaff`. In frontend, it maps to `sales_staff`. Still present in enum and potentially assigned to users. |
| IAM-016 | Medium | Shift endpoints have NO permission middleware | `routes/employee.php` shift routes | 2026-04-06 | All shift routes (`GET /shifts`, `POST /shifts`, `PATCH /shifts/{shift}/end`, etc.) only require `auth:sanctum`. No `permission:manage_shifts` or `permission:view_my_shifts` checks. Any authenticated user (including customers) can create/view/end shifts. |
| IAM-017 | Medium | POS endpoints have no role/permission checks | `routes/employee.php` POS routes | 2026-04-06 | All POS routes (`POST /pos/orders`, `POST /pos/checkout-sessions`, confirm-cash, confirm-card, etc.) only require `auth:sanctum`. No `permission:access_pos` check. A customer with a valid token could potentially create POS orders. |
| IAM-018 | Low | `auth/register` and `auth/quick-register` have no rate limiting | `routes/auth.php` | 2026-04-06 | Only `send-otp` and `verify-otp` have throttle middleware. `register` and `quick-register` are unthrottled. `quick-register` is particularly dangerous as it's public and can create/merge users. |
| IAM-019 | Medium | `orders/by-number/{orderNumber}` is public | `routes/public.php` | 2026-04-06 | Anyone can look up order details by order number. If order numbers are sequential or guessable, this leaks order data (customer info, items, amounts). Need to verify if the controller scopes the response data. |
| IAM-020 | Low | Frontend does not handle `session.revoked` or `user.updated` events | All portals | 2026-04-06 | Backend broadcasts `CustomerSessionEvent` and `StaffSessionEvent` on private channels, but no frontend portal has WebSocket listeners to react to force-logout or permission changes in real-time. |

### 3.2 Resolved Findings

| ID | Severity | Title | Found | Resolved | Resolution |
|----|----------|-------|-------|----------|------------|
| — | — | No resolved findings yet | — | — | — |

### 3.3 Accepted Risks

| ID | Severity | Title | Accepted By | Reason | Review Date |
|----|----------|-------|------------|--------|-------------|
| — | — | No accepted risks yet | — | — | — |

---

## 4. Decision Log

| Date | Decision | Context/Reasoning | Alternatives Considered | Decided By |
|------|----------|-------------------|------------------------|-----------|
| 2026-04-06 | Initial KB created from first comprehensive audit | First activation of IAM Auditor. Full codebase read of both repos. | N/A | IAM Auditor |
| 2026-04-06 | OTP cleanup confirmed scheduled | `routes/console.php` contains `Schedule::command('otp:cleanup')->hourly()`. Previous agent notes said it was missing — corrected. | N/A | IAM Auditor (code verification) |
| 2026-04-06 | Employee legacy role confirmed synced to SalesStaff | RoleSeeder explicitly syncs Employee permissions to match SalesStaff for backward compatibility | Deprecate and remove | Pending developer decision |

---

## 5. Data Retention Policies

| Data Type | Retention Period | Cleanup Method | Schedule | Status |
|-----------|-----------------|---------------|----------|--------|
| Expired OTPs | Purge when expired | `otp:cleanup` artisan command | Hourly | ✅ Implemented |
| Verified OTPs | Unknown — need audit | Unknown | Unknown | ⚠️ Needs verification |
| Guest sessions/carts | No retention policy | None | None | ❌ Not implemented |
| Soft-deleted users | No retention policy | None | None | ❌ Not implemented |
| Soft-deleted customers | N/A (hard deleted) | CustomerController::destroy() | N/A | ⚠️ Hard delete — no soft delete |
| Sanctum tokens | Never expire | None | None | ❌ Not implemented |
| Activity logs | No retention policy | None | None | ❌ Not implemented |
| Password reset tokens | 1-hour expiry per token | Deleted on use via resetPassword() | Per-use only | ⚠️ Expired tokens not bulk-cleaned |

---

## 6. Inter-Agent Contracts

### What IAM Auditor Provides to Other Agents

| Consumer Agent | What We Provide | Contract |
|---------------|-----------------|---------|
| Analytics Agent | Branch-scoping rules, role-permission matrix | Analytics endpoints must verify user has `view_analytics` + branch ownership for manager/partner views |
| Menu Auditor | Public vs admin menu access boundary | Public menu endpoints in `routes/public.php` are read-only GET. Menu mutation requires `permission:manage_menu` via `routes/admin.php` |
| Order Auditor | Order ownership rules, cancellation authorization | ⚠️ Currently: orders in `routes/protected.php` have NO ownership checks. Any auth'd user can CRUD any order. Cancel is now staff-request + admin-approve only (direct cancel route commented out, `CancelRequestController` in admin routes). Refund still unguarded. |
| Project Chronicle | Security findings, permission changes, architecture decisions | All changes logged in KB §7 |

### What IAM Auditor Requires from Other Agents

| Provider Agent | What We Need | Reason |
|---------------|-------------|--------|
| Order Auditor | Confirmation of order-level ownership checks | Need to verify if OrderController::show/update/destroy scope by customer_id or employee assignment |
| Analytics Agent | Confirmation of shift data integrity | Force-logout + shift gap (IAM-010) affects analytics accuracy |

### Shared Definitions

- **"Active User"**: User where `deleted_at IS NULL` AND (if Employee: `employee.status = EmployeeStatus::Active`) AND (if Customer: `customer.status = 'active'`). Note: no enforcement prevents soft-deleted users from having valid tokens.
- **"Authenticated Request"**: Request with valid Sanctum token in Authorization header. Currently tokens never expire.

### Change Notification Log

| Date | Change | Affected Agents | Notified |
|------|--------|----------------|----------|
| 2026-04-06 | Initial audit complete — 20 findings documented | All agents | Pending |

---

## 7. Changelog

| Date | Section Updated | Summary of Change | Trigger |
|------|----------------|-------------------|---------|
| 2026-04-06 | All sections | Initial KB creation. Comprehensive audit of both repos. 20 findings documented (2 Critical, 4 High, 9 Medium, 5 Low). All 7 sections populated. | First activation |
