# CediBites API — Project Chronicle

> **Purpose**: Living record of all changes, decisions, and current state of the CediBites Laravel API. Maintained by the Project Chronicle agent. Read this before starting work on any area.

> **Current Branch**: `payment-order-bug-fixes` (off `master`)

---

## System Map

### Route Files

| Route File             | Domain                                      |
| ---------------------- | ------------------------------------------- |
| `routes/auth.php`      | Registration, login, password reset         |
| `routes/public.php`    | Public menu, branches, categories (no auth) |
| `routes/cart.php`      | Shopping cart operations                    |
| `routes/protected.php` | General authenticated endpoints             |
| `routes/employee.php`  | Employee profile, POS orders, shifts        |
| `routes/manager.php`   | Branch manager: employees, orders, stats    |
| `routes/admin.php`     | Admin CRUD, system management               |
| `routes/promos.php`    | Promo/discount management                   |
| `routes/channels.php`  | WebSocket/Reverb broadcasting channels      |

### Models (29)

**Core**: User, Customer, Employee, Branch, Address
**Menu**: MenuItem, MenuCategory, MenuTag, MenuAddOn, MenuItemOption, MenuItemOptionBranchPrice, MenuItemRating
**Orders**: Order, OrderItem, OrderStatusHistory, ShiftOrder, CheckoutSession
**Shopping**: Cart, CartItem
**Financial**: Payment, Promo
**Operations**: Shift, BranchOperatingHour, BranchDeliverySetting, BranchOrderType, BranchPaymentMethod
**System**: Otp, SystemSetting, ActivityLog

### Services (9)

| Service                  | Purpose                                                                |
| ------------------------ | ---------------------------------------------------------------------- |
| `OrderCreationService`   | Order placement from checkout sessions (DB transaction, lockForUpdate) |
| `OrderManagementService` | Status changes, cancellations                                          |
| `OrderNumberService`     | Order code generation                                                  |
| `HubtelPaymentService`   | Hubtel mobile money gateway                                            |
| `HubtelSmsService`       | SMS via Hubtel                                                         |
| `OTPService`             | OTP generation/validation                                              |
| `PromoResolutionService` | Promo code validation & discount                                       |
| `AnalyticsService`       | Dashboard metrics & reporting                                          |
| `SystemSettingService`   | Global config (cache-backed, 1hr TTL)                                  |

### Key Architecture

- **Payment-first checkout**: CheckoutSession → Payment → OrderCreationService → Order (never direct order creation)
- **Real-time**: Reverb broadcasting on `orders.branch.{id}` and `orders.{number}` channels
- **Order state machine**: received → accepted → preparing → ready → out_for_delivery/ready_for_pickup → delivered/completed
- **Cancel flow**: Staff request → Admin approve/reject
- **Observer pattern**: OrderObserver (lifecycle hooks), PaymentObserver (state transitions)

### Notifications (16)

Order lifecycle (7): NewOrder, Confirmed, Preparing, Ready, OutForDelivery, Completed, Cancelled
Staff (2): AccountCreated, PasswordReset
User auth (2): Welcome, PasswordResetRequired
System (3): OTP, PaymentFailed, HighValueOrder
Business (2): BranchManagerAssigned, BranchManagerRemoved

### Integrations

- **Hubtel Payment Gateway** — Mobile money (Standard for web, RMP for POS USSD)
- **Hubtel SMS** — Notification delivery
- **Reverb** — WebSocket broadcasting
- **Spatie Media Library** — Menu item images
- **Spatie Permission** — Role-based access control
- **Spatie Activity Log** — Audit trail

---

## Change Log

_Entries are added per session, newest first._

<!--
Template for new entries:

## [YYYY-MM-DD] Session: Brief Title

### Intent
What the engineer wanted to achieve.

### Changes Made
| File | Change | Reason |
|------|--------|--------|
| path/to/file.php | Description | Why |

### Decisions
- **Decision**: description
  - **Rationale**: why

### Current State
What the system looks like after changes.

### Pending / Follow-up
Items still needing attention.
-->

---

## [2026-04-04] Session: syncOptions Race Condition Fix — Backend Confirmation (No Changes)

### Intent

Investigate the root cause of "images not showing on customer side" and the "Item saved but image/option sync failed" error reported on the frontend menu management pages. Determine whether the backend's `MenuItemOptionController::destroy()` 422 guard needed modification.

### Changes Made

| File | Change | Reason |
| ---- | ------ | ------ |
| _No backend files changed_ | — | The fix was applied frontend-side only |

### Investigation Findings

The backend's `MenuItemOptionController::destroy()` method returns HTTP 422 when attempting to delete the last remaining option on a menu item (`count() <= 1` guard). This is **correct behavior** — menu items must always have at least one option (the 'standard' option for simple-priced items, or named options for multi-option items).

The bug was in the frontend's `syncOptions()` function ordering: it deleted non-desired options BEFORE creating new ones. When a user replaced ALL option keys with entirely new ones (zero overlap between old and new), the delete loop tried to remove every existing option, hitting the last-option guard. This caused the entire `syncOptions()` promise chain to fail, which also prevented image uploads (the image upload step runs at the end of the chain).

### Decisions

- **Decision**: Backend's `count() <= 1` guard in `destroy()` is correct and should NOT be changed
  - **Rationale**: Menu items must always have at least one option; the guard prevents data integrity issues
- **Decision**: No bulk-sync endpoint needed at this time
  - **Alternatives**: Could create a `PUT /menu-items/{id}/options` bulk-sync endpoint
  - **Rationale**: Frontend reorder (upsert-then-delete) is the minimal fix; a bulk endpoint adds complexity without clear benefit right now

### Cross-Repo Impact

| File (Frontend repo)              | Change                                                                          | Impact                                                                                         |
| --------------------------------- | ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `app/admin/menu/page.tsx`         | Reordered `syncOptions()`: upsert desired options first, then delete stale ones | Fixes the 422 error when replacing all option keys; fixes downstream image upload failure       |
| `app/staff/manager/menu/page.tsx` | Same reorder applied to BM menu page                                            | Same fix applied for consistency                                                               |

### Current State

- `MenuItemOptionController::destroy()` — unchanged, 422 guard for last-option deletion remains
- `MenuItemOptionController::store()` and `update()` — unchanged, work correctly for upsert operations
- The frontend now calls these endpoints in the correct order (create/update first, delete second)
- Branch: `payment-order-bug-fixes`

### Note on Category Slug Fix

The category slug fix from the Menu Management Audit session (adding `slug` to `CreateMenuCategoryRequest` and `UpdateMenuCategoryRequest` validation rules, and auto-generating slug in `MenuCategoryController::store()`) is recorded in the "Menu Management Audit — Full-Stack Fixes" entry above.

### Pending / Follow-up

- Consider a bulk-sync endpoint (`PUT /menu-items/{id}/options`) if frontend option management grows more complex
- `MenuItemOptionController::destroy()` could benefit from a more descriptive error message (e.g., "Cannot delete the last option on a menu item")

---

## [2026-04-04] Session: Menu Management Audit — Full-Stack Fixes

### Intent

Comprehensive audit and fix of the entire menu system across admin and branch manager portals. Fix critical bugs in category CRUD validation, API resource serialization, and broken query logic in multi-branch scenarios.

### Changes Made

| File                                                  | Change                                                                                                                                                                                       | Reason                                                                                                              |
| ----------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `app/Http/Requests/CreateMenuCategoryRequest.php`     | Added `branch_id` required validation; changed name uniqueness from global `unique:menu_categories,name` to branch-scoped `Rule::unique('menu_categories', 'name')->where('branch_id', ...)` | Category names were globally unique but should be unique per-branch (migration has `unique(['branch_id', 'slug'])`) |
| `app/Http/Requests/UpdateMenuCategoryRequest.php`     | Added `branch_id` to rules; scoped name uniqueness to branch with `Rule::unique(...)->where('branch_id', $branchId)->ignore($category)`                                                      | Same branch-scoping fix for updates                                                                                 |
| `app/Http/Resources/MenuCategoryResource.php`         | Added `'branch_id' => $this->branch_id` to output array                                                                                                                                      | Frontend type contract expects branch_id; was missing from serialized response                                      |
| `app/Http/Controllers/Api/MenuCategoryController.php` | Removed `groupBy('name')->map(fn($group) => $group->first())->values()` from `index()`                                                                                                       | Deduplication collapsed categories with same name across branches into one, breaking multi-branch scenarios         |

### Decisions

- **Decision**: Category name uniqueness scoped to branch_id
    - **Rationale**: Migration already defines `unique(['branch_id', 'slug'])` compound index; name uniqueness should follow the same branch scope
- **Decision**: Removed deduplication from category index
    - **Rationale**: Frontend handles filtering by branch_id via query params; server-side dedup broke multi-branch displays

### Cross-Repo Impact

| File (Frontend repo)                                          | Change                                                           | Triggered By                                                                               |
| ------------------------------------------------------------- | ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `types/api.ts`                                                | Added `branch_id?: number` and `slug?: string` to `MenuCategory` | `MenuCategoryResource` now serializes `branch_id`                                          |
| `app/staff/manager/menu/page.tsx`                             | Fixed `useMenuCategories` to pass `branch_id`                    | Categories must be branch-scoped to prevent cross-branch assignment                        |
| `app/admin/menu/page.tsx`                                     | Fixed `toggleGlobal()` to persist via API                        | Availability toggle was frontend-only; needed API round-trip                               |
| `app/admin/menu/page.tsx` + `app/staff/manager/menu/page.tsx` | Added `uploadSimpleImage()`                                      | Backend creates 'standard' option for simple items; frontend wasn't uploading images to it |

### Current State

- Menu categories: CRUD properly branch-scoped, returns branch_id, no dedup
- All 34 menu routes compile
- All modified PHP files pass syntax check
- Branch: `payment-order-bug-fixes`

### Pending / Follow-up

- Consider `MenuManagementService` to centralize scattered controller logic
- Add indexes on frequently queried menu columns for performance at scale
- Menu validation artisan command (detect orphans, missing prices, slug collisions)

---

## [2026-04-04] Session: Settings Toggle Persistence, Delivery Fee Toggle & Global Operating Hours

### Intent

Fix a bug where admin settings toggles reset on page refresh despite saving correctly. Add a `delivery_fee_enabled` toggle to control whether delivery fees appear in checkout. Add global operating hours settings that admins can edit and that display dynamically in the customer-facing footer.

### Changes Made

| File                                                                                      | Change                                                                                                                                                                             | Reason                                                                                                                                                                                                    |
| ----------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Http/Controllers/Api/Admin/SystemSettingController.php`                              | Changed `index()` to return raw `$row->value` strings from DB instead of using `$this->settings->get()` which casts booleans                                                       | **Bug fix**: Frontend compares `s.value === 'true'` (string), but PHP cast returned JSON booleans (`true`/`false`), causing toggles (service charge, manual entry) to always reset to defaults on refresh |
| `database/migrations/2026_04_04_072214_add_delivery_fee_and_operating_hours_settings.php` | **NEW** — Seeds `delivery_fee_enabled` (boolean, default false), `global_operating_hours_open` (string, default "07:00"), `global_operating_hours_close` (string, default "22:00") | New system settings for delivery fee visibility and globally editable operating hours                                                                                                                     |
| `routes/public.php`                                                                       | Expanded `/checkout-config` endpoint to return `delivery_fee_enabled`, `global_operating_hours_open`, `global_operating_hours_close`                                               | Frontend checkout and footer need these values from a public (unauthenticated) endpoint                                                                                                                   |
| `routes/employee.php`                                                                     | Extended settings allowlist to include `delivery_fee_enabled`, `global_operating_hours_open`, `global_operating_hours_close`                                                       | Employee-facing settings endpoints need read access to these values                                                                                                                                       |

### Decisions

- **Decision**: Return raw string values from `SystemSettingController::index()` instead of cast values
    - **Alternatives**: Change frontend to compare booleans instead of strings
    - **Rationale**: The frontend already has string-based comparison (`s.value === 'true'`) established across all settings consumers; changing the API to return strings is less invasive and consistent with how settings are stored
- **Decision**: `delivery_fee_enabled` defaults to `false`
    - **Rationale**: Delivery fees were already "temporarily disabled" in the codebase; defaulting to off maintains current behavior
- **Decision**: Operating hours are global (not per-branch) for now
    - **Alternatives**: Per-branch operating hours (already modeled in `BranchOperatingHour`)
    - **Rationale**: Simpler starting point; can be evolved to per-branch later if needed
- **Decision**: Service charge math (1% of ₵0.10 = ₵0.001 → rounds to ₵0.00) is correct
    - **Rationale**: Mathematically correct rounding behavior, not a bug

### Cross-Repo Impact

| File (Frontend repo)               | Change                                                                                                     | Triggered By                                                                                       |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `app/admin/settings/page.tsx`      | Added "Enable Delivery Fee" toggle in Order Settings tab; General tab operating hours now connected to API | New `delivery_fee_enabled`, `global_operating_hours_open`, `global_operating_hours_close` settings |
| `app/(customer)/checkout/page.tsx` | Delivery fee row conditionally rendered via `deliveryFeeEnabled` prop on `OrderSummary`                    | `delivery_fee_enabled` setting from `/checkout-config`                                             |
| `app/components/layout/Footer.tsx` | Fetches hours from `/checkout-config` instead of hardcoded values; uses `formatTime12h()` helper           | `global_operating_hours_open/close` now available from API                                         |

### Current State

- **Settings toggle persistence**: Fixed — API returns raw strings, frontend string comparisons work correctly
- **Delivery fee**: Controlled by `delivery_fee_enabled` toggle (default off); checkout hides delivery fee row when disabled
- **Operating hours**: Editable from Admin Settings > General tab; displayed dynamically in customer Footer
- **`/checkout-config` endpoint**: Now returns `service_charge_enabled`, `service_charge_percent`, `service_charge_cap`, `delivery_fee_enabled`, `global_operating_hours_open`, `global_operating_hours_close`
- **System settings table**: Now has 6 configurable settings total
- **Branch**: `payment-order-bug-fixes`

### Pending / Follow-up

- Per-branch operating hours could be added if different branches need different schedules
- Consider adding validation for operating hours format (HH:MM) in the API
- Delivery fee amount/calculation logic to be implemented when delivery fees are enabled

---

## [2026-04-04] Session: Order Audit & Security Bug Fixes

### Intent

A comprehensive audit of the order/payment/checkout pipeline was performed across both repos, identifying 7 bugs — including security vulnerabilities (MoMo payment bypass, cross-branch authorization gaps), invalid status transitions, and hardcoded service charge logic. All were fixed in this session on the `payment-order-bug-fixes` branch.

### Changes Made

| File                                                                    | Change                                                                                                                                                        | Reason                                                                                                                                            |
| ----------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Http/Requests/UpdateOrderStatusRequest.php`                        | Removed `pending`, `confirmed`, `cancelled` from allowed status values in validation rules                                                                    | `pending`/`confirmed` are system-set during checkout flow; `cancelled` requires admin cancel approval flow — none should be manually set by staff |
| `app/Http/Controllers/Api/CheckoutSessionController.php`                | Added `->where('payment_method', 'cash')` filter to `confirmCash()` query                                                                                     | **Security fix**: Without this, staff could call confirmCash on a MoMo session, bypassing Hubtel payment gateway verification                     |
| `app/Http/Controllers/Api/CheckoutSessionController.php`                | Added `$this->verifyStaffAuthorization($employee, $session->branch_id)` to `confirmCash()` and `confirmCard()`                                                | **Security fix**: Without this, staff from Branch A could confirm payments for Branch B sessions                                                  |
| `app/Http/Controllers/Api/CheckoutSessionController.php`                | Updated service charge calculation in `store()` to use `service_charge_enabled`, `service_charge_percent`, and `service_charge_cap` from SystemSettingService | Replaced hardcoded 1% with configurable percent + cap; charge is 0 when disabled                                                                  |
| `database/migrations/2026_04_04_060840_add_service_charge_settings.php` | **NEW** — Seeds `service_charge_enabled` (boolean, default true) and `service_charge_cap` (integer, default 5) system settings                                | Complement existing `service_charge_percent` setting; cap ensures charge never exceeds X GHS                                                      |
| `routes/public.php`                                                     | Added `GET /checkout-config` public endpoint returning `service_charge_enabled`, `service_charge_percent`, `service_charge_cap`                               | Frontend needs service charge info before user is authenticated at checkout                                                                       |
| `routes/employee.php`                                                   | Extended settings allowlist to include `service_charge_enabled` and `service_charge_cap`                                                                      | Employee-facing settings endpoints need read access to these values                                                                               |

### Decisions

- **Decision**: Don't touch Hubtel callback `orWhere` scoping (searches by both CheckoutNumber and InvoiceNumber)
    - **Alternatives**: Tighten to `where` + `where` with AND logic
    - **Rationale**: Statistically negligible collision risk, and changing it could break working payment callbacks in production
- **Decision**: Service charge cap defaults to 5 GHS with admin toggle
    - **Rationale**: User requirement — 1% capped at 5 GHS, configurable from admin settings
- **Decision**: Public `/checkout-config` endpoint (no auth required)
    - **Alternatives**: Authenticated endpoint, embed in checkout session response
    - **Rationale**: Frontend needs service charge info before user is authenticated at checkout
- **Decision**: Branch authorization added to `confirmCash()`/`confirmCard()` but not to other checkout session methods
    - **Rationale**: Other methods already have appropriate auth or are called via different flows
- **Decision**: Pint formatting applied to changed PHP files only (`--dirty` flag)
    - **Rationale**: Keep formatting changes scoped to files touched in this session

### Cross-Repo Impact

| File (Frontend repo)               | Change                                                                                                                 | Triggered By                                                          |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| `app/(customer)/checkout/page.tsx` | Added `ServiceChargeConfig` interface, `calcServiceCharge()` helper, `scConfig` state fetching from `/checkout-config` | New public endpoint + dynamic service charge config                   |
| `app/(customer)/checkout/page.tsx` | Updated `OrderSummary` and `StepPayment` to use dynamic service charge with cap                                        | Backend service charge calculation now uses percent + cap             |
| `app/admin/settings/page.tsx`      | Added service charge toggle, percentage input, cap input with conditional rendering                                    | New `service_charge_enabled` and `service_charge_cap` system settings |

### Current State

- **Security**: `confirmCash()` restricted to cash-only sessions; both confirm methods enforce branch authorization
- **Status transitions**: `UpdateOrderStatusRequest` only allows valid staff-settable statuses
- **Service charge**: Fully configurable via SystemSettingService — enabled/disabled toggle, percentage, cap (GHS)
- **System settings**: `service_charge_enabled`, `service_charge_percent`, `service_charge_cap` all in `system_settings` table
- **Pint**: Passes clean
- **Branch**: `payment-order-bug-fixes`

### Bugs Fixed Summary

1. `UpdateOrderStatusRequest` allowed invalid statuses (`pending`, `confirmed`, `cancelled`)
2. `confirmCash()` could be called on non-cash payment sessions (MoMo bypass)
3. `confirmCash()` + `confirmCard()` had no branch authorization check
4. Service charge was hardcoded to 1% with no cap, no admin toggle
5. Frontend used hardcoded service charge calculation, didn't fetch config from API
6. Admin settings UI had no service charge toggle or cap controls
7. Pint formatting applied to changed PHP files

### Pending / Follow-up

- POS checkout flow may also need service charge config integration
- Consider adding rate limiting to the public `/checkout-config` endpoint
- Hubtel callback `orWhere` scoping left as-is — revisit if collision issues arise

---

## [2026-04-04] Session: Project Chronicle Agent Setup

### Intent

Create an institutional memory system for the CediBites project — a "Project Chronicle" agent that silently observes all changes across sessions, records them, and can brief developers and other agents on the current state of any part of the system.

### Changes Made

| File                                                      | Change                                        | Reason                                                                                                     |
| --------------------------------------------------------- | --------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `.github/agents/project-chronicle.agent.md`               | Created agent definition                      | New agent with read/search/edit/agent/todo tools, mandatory cross-referencing, structured chronicle format |
| `.github/instructions/chronicle-reminder.instructions.md` | Created instruction file with `applyTo: "**"` | Auto-reminds all agents to ask user about updating the chronicle after every code change                   |
| `PROJECT_CHRONICLE.md`                                    | Created knowledge base file                   | Seeded with system map (routes, models, services, architecture, integrations)                              |

### Cross-Repo Impact

| File (Frontend repo)                                      | Change                         | Reason                                                                        |
| --------------------------------------------------------- | ------------------------------ | ----------------------------------------------------------------------------- |
| `.github/agents/project-chronicle.agent.md`               | Mirror of API agent definition | Same agent available in both repos                                            |
| `.github/instructions/chronicle-reminder.instructions.md` | Mirror of API instruction      | Reminder works regardless of which repo is being edited                       |
| `PROJECT_CHRONICLE.md`                                    | Frontend knowledge base file   | Seeded with system map (route groups, state management, API layer, utilities) |

### Decisions

- **Decision**: Hybrid approach — seed with system map, build change log incrementally
    - **Alternatives**: Full scan first vs. purely incremental
    - **Rationale**: Gives the agent useful context from day 1 without requiring a massive upfront audit
- **Decision**: Place agent + chronicle in both repos
    - **Rationale**: Agent needs to be discoverable from either workspace; chronicle files track repo-specific changes
- **Decision**: Mandatory cross-referencing between repos
    - **Rationale**: API and frontend are tightly coupled — changes in one often affect the other
- **Decision**: `applyTo: "**"` for the reminder instruction
    - **Rationale**: Should trigger after editing any file type, not just specific ones

### Current State

The Project Chronicle system is fully set up and operational:

- Agent definition in both repos (`.github/agents/project-chronicle.agent.md`)
- Auto-reminder instruction in both repos (`.github/instructions/chronicle-reminder.instructions.md`)
- Knowledge base files in both repo roots (`PROJECT_CHRONICLE.md`)
- System maps seeded; change log ready for entries

### Pending / Follow-up

- First real development session will test the reminder flow end-to-end
- Chronicle entries will accumulate as sessions happen
