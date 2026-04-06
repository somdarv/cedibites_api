# CediBites API — Project Chronicle

> **Purpose**: Living record of all changes, decisions, and current state of the CediBites Laravel API. Maintained by the Project Chronicle agent. Read this before starting work on any area.

> **Current Branch**: `menu-audit` (off `master`)

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

### Models (30)

**Core**: User, Customer, Employee, Branch, Address
**Menu**: MenuItem, MenuCategory, MenuTag, MenuAddOn, MenuItemOption, MenuItemOptionBranchPrice, MenuItemRating, SmartCategorySetting
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
| `SmartCategoryService`   | Smart category orchestration (strategy pattern, 6hr cache TTL)         |

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

## [2026-04-06] Session: Smart Categories Admin Settings — Full CRUD + Service Integration

### Intent

Build the admin configuration backend for smart categories. The initial smart categories system was code-only — categories were computed from enum definitions with no runtime configurability. This session adds a `smart_category_settings` table and full admin CRUD so admins can enable/disable categories, adjust item limits, customize time windows, reorder display, preview resolved items, warm cache, and reset to defaults.

### Changes Made

| File | Change | Reason |
|------|--------|--------|
| `database/migrations/2026_04_06_015552_create_smart_category_settings_table.php` | **NEW** — Creates `smart_category_settings` table with slug (unique), is_enabled, display_order, item_limit, visible_hour_start, visible_hour_end. Seeds 9 default rows from SmartCategory enum. | Runtime configuration for each smart category |
| `app/Models/SmartCategorySetting.php` | **NEW** — Model with fillable, casts (bool/int), `smartCategory()` enum coercion, `hasCustomTimeWindow()` check | Data model for admin-configurable settings per smart category |
| `app/Http/Resources/SmartCategorySettingResource.php` | **NEW** — Returns enriched payload: id, slug, name, icon (from enum), is_enabled, display_order, item_limit, is_time_based, requires_customer, visible/default hour windows, default_item_limit | Frontend needs both custom and default values for UI comparison |
| `app/Http/Requests/UpdateSmartCategorySettingRequest.php` | **NEW** — Validates is_enabled (bool), item_limit (1–50), visible_hour_start/end (nullable, 0–23) | Form Request validation per Laravel conventions |
| `app/Http/Controllers/Api/Admin/SmartCategorySettingController.php` | **NEW** — 6 actions: index, update, reorder, preview, warmCache, resetToDefault. Preview resolves live bypassing cache. WarmCache supports branch-specific or all-branches. | Admin CRUD + operational tools for smart categories |
| `app/Services/SmartCategories/SmartCategoryService.php` | **MODIFIED** — Now loads SmartCategorySetting rows (memoized via `once()`). `getActiveForContext()` checks is_enabled + custom `isVisibleAtHour()`. `resolve()` accepts optional limit, falls back to setting's item_limit. `getResolver()` changed private→public. Added `isVisibleAtHour()` and `getSettingFor()` private methods. | Service must respect admin settings for enable/disable, custom limits, custom time windows |
| `routes/admin.php` | Added 6 routes under `permission:manage_menu`: GET/PATCH smart-categories, POST reorder, GET preview, POST warm-cache, POST reset | Admin-only access to smart category configuration |
| `tests/Feature/SmartCategorySettingTest.php` | **NEW** — 16 Pest tests (170 assertions): model tests, CRUD tests, reorder, reset, preview, warm-cache, service-respects-disabled | Comprehensive test coverage for all new functionality |

### Decisions

- **Decision**: Separate `smart_category_settings` table seeded from enum defaults, rather than adding columns to an existing table
    - **Rationale**: Clean separation of concerns. Migration seeds defaults so the system works immediately. Settings can diverge from enum defaults at runtime.
- **Decision**: `SmartCategoryService` uses `once()` memoization for settings loading
    - **Rationale**: Avoid repeated DB queries within a single request when multiple categories are resolved. Laravel's `once()` is per-request.
- **Decision**: Controller in `Api\Admin` namespace (not root `Api` namespace)
    - **Rationale**: Follows existing admin controller convention. All admin CRUD controllers live under this namespace.
- **Decision**: `getResolver()` changed from private to public on SmartCategoryService
    - **Rationale**: Controller's preview action needs to call resolver directly to bypass cache for live preview.
- **Decision**: Test setup deletes seeded rows in `beforeEach` to avoid unique constraint violations
    - **Rationale**: Migration seeds 9 default rows. Tests creating new rows with same slugs would violate unique constraint. Deleting first ensures clean state.
- **Decision**: Tests use `User` model (not `Employee`) for admin auth
    - **Rationale**: Spatie HasRoles trait is on User model, not Employee. Admin API authentication uses User with roles/permissions.

### Cross-Repo Impact

| File (Frontend repo) | Change | Triggered By |
|------|--------|--------|
| `types/api.ts` | Added `SmartCategorySetting` (14 fields) and `SmartCategoryPreview` interfaces | New API response contracts |
| `lib/api/services/menu.service.ts` | Added 6 methods: getSmartCategorySettings, updateSmartCategorySetting, reorderSmartCategories, previewSmartCategory, warmSmartCategoryCache, resetSmartCategorySetting | Admin API integration |
| `app/admin/menu/page.tsx` | Added "Smart Categories" tab to MENU_SUB_TABS | Navigation to new admin page |
| `app/admin/menu-tags/page.tsx` | Added "Smart Categories" tab to MENU_SUB_TABS | Navigation consistency |
| `app/admin/menu/configure/page.tsx` | Added "Smart Categories" tab to MENU_SUB_TABS | Navigation consistency |
| `app/admin/menu-add-ons/page.tsx` | Added "Smart Categories" tab to MENU_SUB_TABS | Navigation consistency |
| `app/admin/menu/smart-categories/page.tsx` | **NEW** — Full admin UI with category cards (toggle, limit, time windows, reorder, preview, cache warm, reset) | Admin management page for smart categories |

### Current State

- **Models**: 30 (added SmartCategorySetting)
- **Services**: SmartCategoryService now respects runtime settings (enable/disable, custom limits, custom time windows)
- **Tests**: 38 SmartCategory tests pass (253 assertions, 6.67s) — 22 original + 16 new settings tests
- **Admin routes**: 6 new endpoints under `v1/admin/smart-categories/` with `permission:manage_menu`
- **Pint**: Clean
- **Branch**: `menu-audit`

### Pending / Follow-up

- "Staff Picks" tag to replace the manual `popular` tag for curated picks
- Potential future: collaborative filtering for "You Might Like"
- Monitor smart category resolver performance at scale
- Consider adding bulk enable/disable toggle for all smart categories
- Consider drag-and-drop reorder (currently arrow-based) if UX feedback warrants it

---

## [2026-04-06] Session: Smart Categories System — Data-Driven Menu Discovery

### Intent

Replace hardcoded "CediBites Mix" and "Most Popular" categories on the customer-facing menu with a data-driven Smart Categories system. Categories like "Most Popular", "Trending", "Top Rated", "New Arrivals", time-based categories (Breakfast Favorites, Lunch Picks, Dinner Favorites, Late Night Bites), and personalized "Order Again" are now computed from actual order data, ratings, timestamps, and customer history.

### Architecture

- **Pattern**: Strategy pattern — `SmartCategory` enum maps to Resolver classes; `SmartCategoryService` orchestrates resolution + caching
- **Caching**: Laravel Cache with 6-hour TTL; warmed by `menu:compute-smart-categories` artisan command scheduled `everySixHours()`
- **Category ID Convention**: Smart categories use `smart:{slug}` prefix (e.g., `smart:most-popular`) on the frontend to distinguish from regular category names

### Changes Made

| File | Change | Reason |
|------|--------|--------|
| `app/Enums/SmartCategory.php` | **NEW** — Backed PHP enum with 9 cases (MostPopular, Trending, TopRated, NewArrivals, BreakfastFavorites, LunchPicks, DinnerFavorites, LateNightBites, OrderAgain). Methods: `label()`, `icon()`, `requiresCustomer()`, `visibleHours()`, `isTimeBased()`, `isVisibleAtHour()`, `orderHours()`, `defaultLimit()`. Time windows: Breakfast 5–11, Lunch 11–15, Dinner 17–22, LateNight 21–3. | Defines all smart category types as a type-safe enum with metadata |
| `app/Services/SmartCategories/SmartCategoryResolver.php` | **NEW** — Interface with `resolve(int $branchId, int $limit, ?int $customerId = null): Collection` | Contract for all resolver implementations |
| `app/Services/SmartCategories/Resolvers/PopularResolver.php` | **NEW** — Queries order_items + orders (completed, paid) from last 30 days, ranks by SUM(quantity) | Resolves "Most Popular" items |
| `app/Services/SmartCategories/Resolvers/TrendingResolver.php` | **NEW** — Compares order counts in last 7 days vs previous 7 days, ranks by velocity increase | Resolves "Trending Now" items |
| `app/Services/SmartCategories/Resolvers/TopRatedResolver.php` | **NEW** — Filters menu_items with rating >= 4.0 AND rating_count >= 5 | Resolves "Top Rated" items |
| `app/Services/SmartCategories/Resolvers/NewArrivalsResolver.php` | **NEW** — Items with created_at within last 14 days | Resolves "New Arrivals" items |
| `app/Services/SmartCategories/Resolvers/TimeBasedResolver.php` | **NEW** — Accepts SmartCategory enum, uses `EXTRACT(HOUR FROM ...)` for PostgreSQL hour filtering. Handles overnight windows (e.g., 21→3). | Resolves Breakfast/Lunch/Dinner/Late Night categories |
| `app/Services/SmartCategories/Resolvers/OrderAgainResolver.php` | **NEW** — Requires customerId, queries customer's past completed orders ranked by frequency | Resolves personalized "Order Again" items |
| `app/Services/SmartCategories/SmartCategoryService.php` | **NEW** — Orchestrator service. Methods: `getActiveForContext()`, `resolve()`, `warmCacheForBranch()`, `invalidateBranch()`, `hydrateItems()`. Cache key: `smart_category:{slug}:branch:{id}`, TTL: 6 hours. | Central service coordinating all resolvers with caching |
| `app/Console/Commands/ComputeSmartCategories.php` | **NEW** — Artisan command `menu:compute-smart-categories {--branch=}`. Warms cache for all active branches or a specific one. | Pre-computes smart categories on schedule |
| `app/Http/Controllers/Api/SmartCategoryController.php` | **NEW** — `index(Request)` validates `branch_id`, calls SmartCategoryService, returns JSON | Public API endpoint for smart categories |
| `routes/public.php` | Added `Route::get('smart-categories', ...)` and import | Public endpoint (no auth required) |
| `routes/console.php` | Added `Schedule::command('menu:compute-smart-categories')->everySixHours()` | Automated cache warming |
| `database/factories/OrderFactory.php` | Fixed `tax_rate`/`tax_amount` → `service_charge` | Factory had stale column names that don't exist in DB (renamed to service_charge previously) |
| `tests/Feature/SmartCategoryTest.php` | **NEW** — 22 Pest tests (83 assertions): enum tests, all 6 resolver tests, service tests (active context, guest exclusion, caching, invalidation), API endpoint tests | Comprehensive test coverage |

### Decisions

- **Decision**: Strategy pattern over config table — resolvers are code-defined because each has unique query logic
    - **Alternatives**: Database-driven config table for category definitions
    - **Rationale**: No migration needed — just enum + services. Each resolver has unique query logic that doesn't fit a generic config model.
- **Decision**: Cache layer instead of materialized views
    - **Alternatives**: PostgreSQL materialized views
    - **Rationale**: Laravel Cache with 6-hour TTL is simpler and sufficient; no DB schema changes required
- **Decision**: `smart:` prefix convention on frontend to distinguish smart vs regular categories
    - **Alternatives**: Separate data structures or boolean flag
    - **Rationale**: String prefix is clean, simple, and requires no structural changes to category handling
- **Decision**: PostgreSQL `EXTRACT(HOUR FROM ...)` instead of MySQL `HOUR()`
    - **Rationale**: Required for PostgreSQL compatibility. Initially used MySQL syntax which failed in tests.
- **Decision**: Route prefix `v1` not `api`
    - **Rationale**: App uses `apiPrefix: 'v1'` in bootstrap/app.php. Tests initially used `/api/` prefix which caused 404s.
- **Decision**: No migration needed — smart categories are computed at runtime and cached
    - **Rationale**: Avoids schema changes; data is derived from existing order/menu tables
- **Decision**: Fix OrderFactory `tax_rate`/`tax_amount` → `service_charge`
    - **Rationale**: Factory had stale column names from a previous schema rename; discovered during test debugging

### Cross-Repo Impact

| File (Frontend repo) | Change | Triggered By |
|------|--------|--------|
| `types/api.ts` | Added `SmartCategory` interface: `{ slug, name, icon, item_ids }` | New smart categories API response contract |
| `lib/api/services/menu.service.ts` | Added `getSmartCategories(branchId)` | API integration for smart categories |
| `lib/api/hooks/useSmartCategories.ts` | **NEW** — TanStack Query hook with 10-minute staleTime | React Query hook for data fetching |
| `app/components/providers/MenuDiscoveryProvider.tsx` | Integrated smart categories, replaced hardcoded "Most Popular" logic | Dynamic smart category integration |
| `app/components/ui/MenuGrid.tsx` | Replaced hardcoded "Most Popular" section with generic smart category section | Dynamic rendering of active smart category |
| `app/(customer)/menu/page.tsx` | Categories now objects `{id, label}` with smart category support | Mixed category types (smart + regular) |

### Current State

- **Tests**: 22 Pest tests pass (83 assertions, 6.79s)
- **Formatting**: Pint passes clean
- **API endpoint**: `GET /v1/smart-categories?branch_id={id}` — public, no auth required
- **Scheduled task**: `menu:compute-smart-categories` runs every 6 hours
- **Smart categories**: MostPopular, Trending, TopRated, NewArrivals, BreakfastFavorites, LunchPicks, DinnerFavorites, LateNightBites, OrderAgain
- **Branch**: `menu-audit`

### Pending / Follow-up

- Admin settings UI for toggling/configuring smart categories (enable/disable individual categories, adjust limits)
- "Staff Picks" tag to replace the manual `popular` tag for curated picks
- Potential future: collaborative filtering for "You Might Like"
- Consider adding smart category configuration to the admin menu management page
- Monitor smart category resolver performance at scale — may need query optimization or index additions

---

## [2026-04-05] Session: Menu Item Image Display Fix — Route-Based Media Serving

### Intent

Fix menu item images not displaying on customer-facing menu pages. API returned image URLs like `https://beta-api.cedibites.com/storage/17/...` but Nginx returned 403 Forbidden for `/storage/` paths. Images were stored correctly in Spatie Media Library but inaccessible via the web. Solution: serve media through a Laravel controller that bypasses Nginx static file restrictions, with proper caching headers and thumbnail support.

### Changes Made

| File                                            | Change                                                                                                                                                                                                                                                            | Reason                                                                                                |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `app/Http/Controllers/Api/MediaController.php`  | **NEW** — Controller that serves media files through Laravel. Includes ETag/Last-Modified headers, 304 Not Modified conditional caching, `Cache-Control: public, max-age=31536000, immutable`. Supports optional `/{conversion}` parameter for thumbnail serving. | Bypasses Nginx 403 on `/storage/` paths; gives application-level control over caching and conversions |
| `routes/public.php`                             | Added `GET /v1/media/{media}/{conversion?}` public route named `media.show`                                                                                                                                                                                       | Public endpoint for all media access — no auth required for menu images                               |
| `app/Http/Resources/MenuItemResource.php`       | `image_url` now uses `route('media.show', $media)` instead of `getFirstMediaUrl()`. Added `thumbnail_url` field using `route('media.show', [$media, 'thumbnail'])`                                                                                                | Route-based URLs are always accessible; thumbnails enable faster grid loading                         |
| `app/Http/Resources/MenuItemOptionResource.php` | Same changes as MenuItemResource: route-based `image_url` and new `thumbnail_url`                                                                                                                                                                                 | Options also have images; same fix needed                                                             |
| `app/Models/MenuItemOption.php`                 | Added Spatie `registerMediaConversions()` with `thumbnail` conversion (400×300px, sharpened, `nonQueued()`)                                                                                                                                                       | Thumbnails generated synchronously on upload for option images                                        |

### Decisions

- **Decision**: Serve media through Laravel (MediaController) rather than fixing Nginx config
    - **Alternatives**: Fix Nginx config to allow `/storage/` access; use a CDN
    - **Rationale**: (a) Can't guarantee server Nginx config access in all environments, (b) application-level caching headers (ETag, 304), (c) supports conversion parameter for thumbnails, (d) works identically in dev and production
- **Decision**: `Cache-Control: public, max-age=31536000, immutable` on media responses
    - **Rationale**: Spatie uses unique media IDs — once an image is uploaded, its URL never changes. The image at `/v1/media/17` is permanent. Immutable caching is safe and maximizes CDN/browser cache hits.
- **Decision**: Thumbnail conversion is `nonQueued()` — generated synchronously on upload
    - **Alternatives**: Queued conversion via Laravel jobs
    - **Rationale**: Menu images are uploaded infrequently (admin action, not user-facing); sync generation is simpler and avoids "thumbnail not ready" race conditions
- **Decision**: 400×300px thumbnail size with sharpening
    - **Rationale**: Matches typical card grid display size; sharpening compensates for downscale blur

### Cross-Repo Impact

| File (Frontend repo)                                                                        | Change                                                              | Triggered By                                                            |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `next.config.ts`                                                                            | Removed `unoptimized: true`, added `remotePatterns` for API domains | Image URLs now point to API host — Next.js needs to proxy/optimize them |
| `types/api.ts`                                                                              | Added `thumbnail_url` to `MenuItem` and `MenuItemOption` interfaces | New `thumbnail_url` field in API resources                              |
| `app/components/providers/MenuDiscoveryProvider.tsx`                                        | Maps `thumbnail_url` → `thumbnail` in search items                  | Consumer of new thumbnail data                                          |
| `app/components/ui/MenuItemCard.tsx`                                                        | Prefers `thumbnail` over `image` for grid display                   | Performance optimization using new thumbnails                           |
| `lib/utils/compressImage.ts`, `lib/api/services/menu.service.ts`, `app/admin/menu/page.tsx` | Removed debug `console.log` statements                              | Cleanup from previous image upload session                              |

### Current State

- **Media serving**: All menu images served via `GET /v1/media/{media}/{conversion?}` — no more Nginx 403
- **Caching**: ETag + Last-Modified + immutable Cache-Control on all media responses; 304 Not Modified for conditional requests
- **Thumbnails**: `MenuItemOption` model has `thumbnail` conversion (400×300, sharpened, sync). `MenuItem` thumbnails depend on whether the model already had `registerMediaConversions()`.
- **API Resources**: Both `MenuItemResource` and `MenuItemOptionResource` return `image_url` (route-based) and `thumbnail_url`
- **Route**: `media.show` registered in `routes/public.php`, no auth required
- **Branch**: `payment-order-bug-fixes`

### Pending / Follow-up

- Run `php artisan media-library:regenerate --only-missing` on production to backfill thumbnails for existing images
- Verify `MenuItem` model also has `registerMediaConversions()` with thumbnail — if not, add it for consistency
- Consider adding rate limiting to the media endpoint if abuse is a concern
- Monitor media serving performance — if high traffic, consider adding a CDN layer in front

---

## [2026-04-04] Session: syncOptions Race Condition Fix — Backend Confirmation (No Changes)

### Intent

Investigate the root cause of "images not showing on customer side" and the "Item saved but image/option sync failed" error reported on the frontend menu management pages. Determine whether the backend's `MenuItemOptionController::destroy()` 422 guard needed modification.

### Changes Made

| File                       | Change | Reason                                 |
| -------------------------- | ------ | -------------------------------------- |
| _No backend files changed_ | —      | The fix was applied frontend-side only |

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

| File (Frontend repo)              | Change                                                                          | Impact                                                                                    |
| --------------------------------- | ------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `app/admin/menu/page.tsx`         | Reordered `syncOptions()`: upsert desired options first, then delete stale ones | Fixes the 422 error when replacing all option keys; fixes downstream image upload failure |
| `app/staff/manager/menu/page.tsx` | Same reorder applied to BM menu page                                            | Same fix applied for consistency                                                          |

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
