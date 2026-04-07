# Analytics Auditor Knowledge Base

> **Created:** April 7, 2026
> **Last Updated:** April 7, 2026 — Initial full audit

---

## §1 — Canonical Metric Definitions

> ⚠️ **PENDING DEVELOPER CONFIRMATION** — These definitions are inferred from the most rigorous computation path (AnalyticsService). The developer must confirm each one.

| Metric | Canonical Definition | Source of Truth |
|--------|---------------------|-----------------|
| **Revenue** | `SUM(orders.total_amount)` WHERE `status != 'cancelled'` AND has payment with `payment_status = 'completed'`. Excludes no_charge orders. | `AnalyticsService::getSalesAnalytics()` |
| **Total Orders** | `COUNT(*)` of orders matching `paymentConfirmed()` scope (has at least one payment with `payment_status IN ['completed', 'no_charge']`). Includes cancelled orders. | `AnalyticsService::getSalesAnalytics()` |
| **Average Order Value** | `Revenue / Total Orders`. Note: denominator includes no_charge and cancelled orders — this deflates the average. | `AnalyticsService::getSalesAnalytics()` |
| **Active Order** | Status IN `['received', 'confirmed', 'preparing', 'ready', 'ready_for_pickup', 'out_for_delivery']` | `AdminDashboardController::index()` |
| **Completed Order** | Status IN `['completed', 'delivered']` | Used in `getBranchPerformanceAnalytics()`, `getDailyReport()`, `getMonthlyReport()`, `getCustomerAnalytics()` |
| **Cancelled Order** | `status = 'cancelled'` | Consistent across all sources |
| **No-Charge Order** | Has payment with `payment_status = 'no_charge'`. Revenue tracked separately, not in main revenue total. | `AnalyticsService::getSalesAnalytics()` |
| **paymentConfirmed() Scope** | `whereHas('payments', fn($q) => $q->whereIn('payment_status', ['completed', 'no_charge']))` | `Order::scopePaymentConfirmed()` |

### Questions for Developer

1. **AOV denominator**: Should `average_order_value` divide revenue by ALL paymentConfirmed orders (including cancelled/no_charge), or only by completed paid orders?
2. **Revenue and no_charge**: Should no_charge orders contribute to "revenue" for reporting purposes (e.g., staff meals have real cost)?
3. **Cancelled order revenue**: Is `cancelled_revenue_today` meant to show "revenue lost" or "revenue at risk"? Should it only count orders that had completed payments?
4. **What statuses define "active"**: Is `confirmed` a real status used in the system? The `OrderBroadcastEvent` suggests a state machine — verify the active set.

---

## §2 — Backend Analytics Source Map

### 2.1 AnalyticsService (Single Source of Truth — intended)

| Method | Lines | Filters | Revenue Definition | Notes |
|--------|-------|---------|-------------------|-------|
| `getSalesAnalytics()` | 17-80 | paymentConfirmed + date + branch | `status != cancelled` + `payment_status = completed` | ✅ Canonical |
| `getOrderAnalytics()` | 85-157 | `Order::query()` (NO paymentConfirmed) + date + branch | N/A (counts only) | ⚠️ Counts ALL orders including ones without valid payments |
| `getCustomerAnalytics()` | 161-264 | Various | `status IN ['completed', 'delivered']` for spending | ⚠️ No payment_status filter — different revenue def |
| `getOrderSourceAnalytics()` | 267-292 | paymentConfirmed + `status != cancelled` + date + branch | Avg value only | ✅ Consistent |
| `getTopItemsAnalytics()` | 298-348 | `payment_status IN ['completed', 'no_charge']` + `status != cancelled` | Includes no_charge in item revenue | ⚠️ Includes no_charge unlike main revenue |
| `getBottomItemsAnalytics()` | 349-389 | Same as top items | Same as top items | ⚠️ Same no_charge inclusion |
| `getCategoryRevenueAnalytics()` | 390-426 | `payment_status IN ['completed', 'no_charge']` + `status != cancelled` | Category-level, includes no_charge | ⚠️ Includes no_charge unlike main revenue |
| `getBranchPerformanceAnalytics()` | 428-469 | `Order::query()` (NO paymentConfirmed, NO payment filter) + date + branch | `status IN ['completed', 'delivered']` | ⚠️ No payment filter at all — counts all orders |
| `getDeliveryPickupAnalytics()` | 470-495 | paymentConfirmed + `status != cancelled` | N/A (counts only) | ✅ Consistent |
| `getPaymentMethodAnalytics()` | 496-602 | Payment join, `payment_status = completed` + `status != cancelled` | N/A (percentages) | ✅ Consistent |
| `getDailyReport()` | 603-624 | `whereDate` only, NO paymentConfirmed | `status IN ['completed', 'delivered']` | ⚠️ No payment filter — includes unpaid orders |
| `getMonthlyReport()` | 625-668 | `whereYear+whereMonth`, NO paymentConfirmed | `status IN ['completed', 'delivered']` | ⚠️ No payment filter — includes unpaid orders |

### 2.2 AdminDashboardController (Inline — duplicates AnalyticsService)

| Metric | Line | Filters | Matches AnalyticsService? |
|--------|------|---------|--------------------------|
| `revenue_today` | ~30 | paymentConfirmed + `status != cancelled` + `payment_status = completed` + today | ✅ YES |
| `orders_today` | ~36 | paymentConfirmed + today | ✅ YES (same denominator as total_orders) |
| `active_orders` | ~37 | paymentConfirmed + status IN active set | ✅ Unique metric (not in AnalyticsService) |
| `cancelled_today` | ~38 | paymentConfirmed + today + `status = cancelled` | ✅ Consistent |
| `no_charge_today` | ~39 | paymentConfirmed + today + `payment_status = no_charge` | ✅ Consistent |
| Per-branch `revenue_today` | ~46 | Same as global revenue_today but branch-scoped | ✅ Consistent |
| Per-branch `orders_today` | ~45 | paymentConfirmed + today + branch | ✅ Consistent |

**Verdict**: Logic is correct and consistent, but it **duplicates** AnalyticsService and should delegate instead.

### 2.3 BranchController (Inline — DIVERGENT)

| Method | Metric | Filters | Matches AnalyticsService? | Severity |
|--------|--------|---------|--------------------------|----------|
| `index()` | `today_revenue` | `status IN ['completed', 'delivered']` only. NO payment_status filter. NO paymentConfirmed. | ❌ **CRITICAL DIVERGENCE** | 🔴 |
| `index()` | `today_orders` | paymentConfirmed + today | ✅ Consistent | — |
| `stats()` | `today_revenue` / `month_revenue` | paymentConfirmed + `status != cancelled` + `payment_status = completed` | ✅ Consistent | — |
| `stats()` | `today_orders` / `month_orders` | paymentConfirmed + date | ✅ Consistent | — |
| `topItems()` | top items by qty | paymentConfirmed + `status != cancelled` + **in-memory aggregation** | ⚠️ Same filter logic, but PHP iteration instead of DB aggregation | 🟡 |
| `revenueChart()` | daily revenue | paymentConfirmed + `status != cancelled` + SUM(total_amount) | ⚠️ No payment_status filter — includes no_charge in revenue | 🟠 |
| `staffSales()` | per-staff breakdown | `status != cancelled` + NO paymentConfirmed | ⚠️ Could include orders with pending/failed payments | 🟠 |

### 2.4 OrderManagementService::getBranchStats()

| Metric | Filters | Matches? | Severity |
|--------|---------|----------|----------|
| `today_revenue` | `payment_status = completed` + `status != cancelled` + today | ✅ Consistent | — |
| `pending_orders` | paymentConfirmed + `status = received` | ✅ | — |
| `preparing_orders` | paymentConfirmed + status IN `['preparing', 'ready', 'ready_for_pickup', 'out_for_delivery']` | ⚠️ Missing `confirmed` status (Dashboard includes it in active) | 🟡 |
| `completed_today` | `status IN ['completed', 'delivered']` + today. NO paymentConfirmed. | ⚠️ Could include unpaid orders | 🟡 |

### 2.5 PaymentController::stats()

| Metric | Filters | Notes |
|--------|---------|-------|
| Payment totals by status | Groups by `payment_status` | ✅ Unique metric, not duplicated |
| No-charge total | Joins Order, sums `order.total_amount` | ✅ Correctly handles zero-amount payments |

### 2.6 ShiftController (Denormalized Counters)

| Field | Update Logic | Reconciliation | Severity |
|-------|-------------|----------------|----------|
| `Shift.total_sales` | `increment()` when ShiftOrder created | ❌ NONE — never decremented on cancel/refund | 🔴 |
| `Shift.order_count` | `increment()` when ShiftOrder created | ❌ NONE — never decremented on cancel | 🔴 |

---

## §3 — Frontend Analytics Surface Map

### 3.1 Admin Dashboard (`app/admin/dashboard/page.tsx`)

| KPI Displayed | Backend Endpoint | Client Computation? |
|---------------|-----------------|---------------------|
| Revenue Today | `GET /admin/dashboard` → `kpis.revenue_today` | No |
| Orders Today | Same → `kpis.orders_today` | No |
| Active Orders | Same → `kpis.active_orders` | No |
| Cancelled Today | Same → `kpis.cancelled_today` | No |
| No-Charge Today | Same → `kpis.no_charge_today` | No |
| Per-Branch Revenue/Orders | Same → `branches[]` | No |
| Live Orders Feed | Same → `live_orders[]` | No |

**Stale Time**: 60s. **Risk**: Low — purely backend data.

### 3.2 Admin Analytics (`app/admin/analytics/page.tsx` ~72KB)

| Tab/Section | Backend Endpoints | Client Computation? |
|-------------|------------------|---------------------|
| Sales | `/admin/analytics/sales` | No |
| Orders | `/admin/analytics/orders` | No |
| Customers | `/admin/analytics/customers` | No |
| Order Sources | `/admin/analytics/order-sources` | No |
| Top/Bottom Items | `/admin/analytics/top-items`, `bottom-items` | No |
| Category Revenue | `/admin/analytics/category-revenue` | No |
| Branch Performance | `/admin/analytics/branch-performance` | No |
| Delivery/Pickup | `/admin/analytics/delivery-pickup` | No |
| Payment Methods | `/admin/analytics/payment-methods` | No |

**Stale Time**: 120s. **Risk**: Low — all delegated to AnalyticsService.

### 3.3 Admin Transactions (`app/admin/transactions/page.tsx`)

| Data | Backend Endpoint | Client Computation? |
|------|-----------------|---------------------|
| Payment list | `GET /admin/payments` | No |
| Stats cards | `GET /admin/payments/stats` | No |

**Stale Time**: 120s. **Risk**: Low — PaymentController is unique, not duplicated.

### 3.4 Manager Dashboard (`app/staff/manager/dashboard/page.tsx`)

| KPI | Backend Endpoint | Client Computation? |
|-----|-----------------|---------------------|
| Today Revenue/Orders | `GET /manager/branches/{id}/stats` | ⚠️ AOV = revenue/orders client-side |
| Weekly Revenue Chart | `GET /manager/branches/{id}/revenue-chart` | No |
| Top Items | `GET /manager/branches/{id}/top-items` | No |

**Stale Time**: 300s (5 min). **Risk**: Manager calls `BranchController::stats()` which is CONSISTENT, but `BranchController::revenueChart()` includes no_charge in revenue.

### 3.5 Manager Analytics (`app/staff/manager/analytics/page.tsx` ~65KB)

| Section | Backend Endpoints | Notes |
|---------|------------------|-------|
| Sales overview | `/admin/analytics/sales?branch_id=X` | ✅ Uses AnalyticsService |
| Orders | `/admin/analytics/orders?branch_id=X` | ✅ Uses AnalyticsService |
| Top/Bottom Items | `/admin/analytics/top-items?branch_id=X` | ✅ Uses AnalyticsService |
| Payment Methods | `/admin/analytics/payment-methods?branch_id=X` | ✅ Uses AnalyticsService |

**Risk**: LOW — Manager analytics page correctly uses admin analytics endpoints (AnalyticsService) with branch filter. The manager **dashboard** uses BranchController though.

### 3.6 Manager Staff Sales (`app/staff/manager/staff-sales/page.tsx`)

| Data | Backend Endpoint | Client Computation? |
|------|-----------------|---------------------|
| Per-staff breakdown | `GET /manager/branches/{id}/staff-sales?date=X` | Grand totals computed client-side |

**Risk**: MEDIUM — `staffSales()` doesn't use paymentConfirmed scope.

### 3.7 Partner Dashboard & Analytics

| Page | Endpoints Used | Notes |
|------|---------------|-------|
| Dashboard | `/admin/analytics/sales`, `/admin/analytics/orders`, `/admin/orders` with branch filter | ✅ Uses AnalyticsService |
| Analytics | Same admin analytics endpoints with branch filter | ✅ Uses AnalyticsService |

**Risk**: LOW — Partners correctly use AnalyticsService via admin routes.

### 3.8 Staff Dashboard & My Sales

| Page | Endpoints Used | Notes |
|------|---------------|-------|
| Staff Dashboard | `employee/orders/stats` → `OrderManagementService::getBranchStats()` | ⚠️ See §2.4 divergence notes |
| My Sales | Shift-based data via employee endpoints | ⚠️ Depends on denormalized shift counters |

---

## §4 — Divergence Registry

### §4.1 — Open Divergences

| # | Severity | Location A | Location B | Divergence Detail | Impact |
|---|----------|-----------|-----------|-------------------|--------|
| **D1** | 🔴 CRITICAL | `BranchController::index()` today_revenue | All other revenue computations | Uses `status IN ['completed', 'delivered']` with NO payment_status filter and NO paymentConfirmed scope. Could count orders with pending/failed payments as revenue. | Admin branches list shows wrong per-branch revenue |
| **D2** | 🔴 CRITICAL | `Shift.total_sales` / `Shift.order_count` | Actual ShiftOrder sum / count | Denormalized counters only increment (via `addOrder()`). Never decremented on cancel/refund. Drift accumulates over time. | Staff "My Sales" page shows inflated numbers |
| **D3** | 🟠 HIGH | `AdminDashboardController` | `AnalyticsService` | Dashboard computes all KPIs inline instead of delegating to AnalyticsService. Logic currently matches, but any future AnalyticsService change won't be reflected on the dashboard. | Maintenance risk — two places to update |
| **D4** | 🟠 HIGH | `BranchController::revenueChart()` | `AnalyticsService::getSalesAnalytics()` revenue | revenueChart uses `paymentConfirmed + status != cancelled` but NO explicit `payment_status = completed` filter. Since paymentConfirmed includes no_charge, chart includes no_charge orders in revenue. AnalyticsService excludes them. | Manager dashboard weekly chart inflated by no_charge orders |
| **D5** | 🟠 HIGH | `BranchController::staffSales()` | No AnalyticsService equivalent | No `paymentConfirmed()` scope. Could include orders with only pending/failed payments. Also, `total_revenue` excludes no_charge (correct) but no payment_status check on the included payments. | Staff sales report could be inaccurate |
| **D6** | 🟡 MEDIUM | `BranchController::topItems()` | `AnalyticsService::getTopItemsAnalytics()` | BranchController loads ALL orders into memory and iterates in PHP. AnalyticsService uses DB-level aggregation. Same logical filters but different performance characteristics and potential for subtle differences (rounding, null handling). | Performance issue at scale; potential number differences |
| **D7** | 🟡 MEDIUM | `AnalyticsService::getBranchPerformanceAnalytics()` | `AnalyticsService::getSalesAnalytics()` | Branch performance uses `status IN ['completed', 'delivered']` for revenue with NO payment filter. Sales analytics uses `payment_status = completed`. | Branch performance revenue ≠ sales analytics revenue for same branch |
| **D8** | 🟡 MEDIUM | `AnalyticsService::getDailyReport()` / `getMonthlyReport()` | `AnalyticsService::getSalesAnalytics()` | Reports use `status IN ['completed', 'delivered']` with NO paymentConfirmed, NO payment_status filter. In-memory collection aggregation instead of DB queries. | Daily/monthly report revenue ≠ sales analytics revenue |
| **D9** | 🟡 MEDIUM | `AnalyticsService::getCustomerAnalytics()` top_customers_by_spending | `AnalyticsService::getSalesAnalytics()` | Customer spending uses `status IN ['completed', 'delivered']` with NO payment filter. | Top customer spending may not match actual paid revenue |
| **D10** | 🟡 MEDIUM | `AnalyticsService::getOrderAnalytics()` total_orders | `AnalyticsService::getSalesAnalytics()` total_orders | Order analytics counts ALL orders (`Order::query()` — no paymentConfirmed). Sales analytics counts only paymentConfirmed orders. Both are labeled `total_orders`. | Same label, different numbers on different analytics tabs |
| **D11** | 🟢 LOW | `OrderManagementService::getBranchStats()` preparing_orders | `AdminDashboardController` active_orders | Staff dashboard "preparing" = `['preparing', 'ready', 'ready_for_pickup', 'out_for_delivery']`. Admin "active" adds `['received', 'confirmed']`. Different definitions, different labels — acceptable if intentional. | Minor — different metrics for different audiences |
| **D12** | 🟢 LOW | `AnalyticsService` top/bottom/category items | `AnalyticsService::getSalesAnalytics()` revenue | Item/category analytics include `no_charge` orders in revenue. Sales analytics excludes no_charge from revenue. | Item revenue totals won't sum to total sales revenue |

### §4.2 — Resolved Divergences

_None yet._

### §4.3 — Accepted Risks

_None confirmed yet — awaiting developer review._

---

## §5 — Denormalized Counter Audit

### Shift Counters

| Counter | Model | Update Mechanism | Decrement on Cancel? | Decrement on Refund? | Reconciliation Job? |
|---------|-------|-----------------|---------------------|---------------------|-------------------|
| `total_sales` | Shift | `$shift->increment('total_sales', $orderTotal)` in `ShiftController::addOrder()` | ❌ NO | ❌ NO | ❌ NONE |
| `order_count` | Shift | `$shift->increment('order_count')` in `ShiftController::addOrder()` | ❌ NO | ❌ NO | ❌ NONE |

**Recommendation**: Either (a) compute totals on-the-fly from ShiftOrder JOIN Order with status/payment filters, or (b) add decrement logic in the Order cancellation flow and create a nightly reconciliation job.

---

## §6 — Caching & Performance Notes

| What | Cached? | TTL | Invalidation |
|------|---------|-----|-------------|
| AnalyticsService methods | ❌ No caching | — | — |
| AdminDashboardController | ❌ No caching | — | — |
| BranchController stats/topItems/revenueChart | ❌ No caching | — | — |
| Frontend TanStack Query | ✅ Client-side | 60s-300s per hook | Time-based staleTime |

**Recommendation**: Add cache layer to AnalyticsService for read-heavy endpoints. Invalidate on OrderBroadcastEvent.

---

## §7 — Decision Log

| Date | Decision | Reasoning | Status |
|------|----------|-----------|--------|
| 2026-04-07 | Initial audit completed | Full audit of all 8+ computation locations. 12 divergences identified. | Active |
| 2026-04-07 | Revenue definition proposed | `status != cancelled` + `payment_status = completed` as canonical. Excludes no_charge. | Pending developer confirmation |

---

## §8 — Inter-Agent Contracts

| Agent | Contract | Status |
|-------|----------|--------|
| Order Auditor | "Completed order" = status IN ['completed', 'delivered']. Cancel flow must fire analytics invalidation. | Pending alignment |
| Menu Auditor | Item analytics use `menu_items.id` + `menu_item_options` join. Menu restructure must preserve IDs or analytics break. | Noted |
| IAM Auditor | Analytics role-scoping: admin=all, manager=branch, partner=branch(readonly), staff=self. Currently enforced at route level only. | Noted |

---

## §9 — Changelog

| Date | Change | Author |
|------|--------|--------|
| 2026-04-07 | Created KB. Full initial audit: 12 divergences found, 16 untapped data sources identified, 0 caching, shift counter drift confirmed. | Analytics Auditor |
