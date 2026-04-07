# Analytics Auditor Knowledge Base

> **Created:** April 7, 2026
> **Last Updated:** April 7, 2026 — Post-overhaul: unified analytics engine deployed

---

## §1 — Canonical Metric Definitions

> ✅ **DEVELOPER CONFIRMED** — All definitions locked in on April 7, 2026.

| Metric                               | Canonical Definition                                                                                                          | Source of Truth                                     |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| **Placed Order**                     | Has payment with `payment_status IN ['completed', 'no_charge']` (`paymentConfirmed()` scope)                                  | `AnalyticsQueryBuilder::placedOrders()`             |
| **Revenue**                          | `SUM(orders.total_amount)` WHERE placed + `status != 'cancelled'` + `payment_status = 'completed'` (excludes no_charge)       | `AnalyticsQueryBuilder::computeRevenue()`           |
| **Revenue-Contributing Order Count** | COUNT of placed orders WHERE `status != 'cancelled'` + `payment_status = 'completed'`                                         | `AnalyticsQueryBuilder::computeRevenueOrderCount()` |
| **No-Charge Order**                  | Placed, `payment_status = 'no_charge'`, counted in orders but NOT in revenue. Tracked separately with own amount.             | `AnalyticsQueryBuilder::noChargeOrders()`           |
| **AOV**                              | `revenue / revenue_contributing_order_count` (excludes no_charge and cancelled from denominator)                              | `AnalyticsService::getSalesMetrics()`               |
| **Active Order**                     | Status IN `['received', 'accepted', 'preparing', 'ready', 'ready_for_pickup', 'out_for_delivery']`                            | `AnalyticsQueryBuilder::ACTIVE_STATUSES`            |
| **Completed Order**                  | Status IN `['completed', 'delivered']`                                                                                        | `AnalyticsQueryBuilder::COMPLETED_STATUSES`         |
| **Cancelled Order**                  | `status = 'cancelled'`. Auto-refund fires: if `payment_status = 'completed'` → flipped to `'refunded'`. No_charge left as-is. | `OrderObserver::handleCancellationSideEffects()`    |
| **Cancel = Auto-Refund**             | When cancelled: completed→refunded, no_charge→unchanged. Shift counters decremented.                                          | `OrderObserver::handleCancellationSideEffects()`    |

---

## §2 — Backend Analytics Source Map

### Architecture: One Source, One Pipeline

```
AnalyticsQueryBuilder (canonical queries)
    ↓
AnalyticsService (unified engine — 19 public methods)
    ↓
Controllers (thin wrappers — zero inline computation)
    ↓
Frontend (all portals tap same pipe with different filters)
```

### 2.1 AnalyticsQueryBuilder (`app/Services/Analytics/AnalyticsQueryBuilder.php`)

The canonical query factory. Every analytics computation MUST use these builders.

| Method                               | Returns                                                              | Filters Applied                    |
| ------------------------------------ | -------------------------------------------------------------------- | ---------------------------------- |
| `placedOrders($filters)`             | Builder: paymentConfirmed + status != cancelled                      | date, branch, employee             |
| `revenueOrders($filters)`            | Builder: placed + payment_status = completed                         | date, branch, employee             |
| `noChargeOrders($filters)`           | Builder: placed + payment_status = no_charge                         | date, branch, employee             |
| `cancelledOrders($filters)`          | Builder: paymentConfirmed + status = cancelled                       | date, branch, employee             |
| `activeOrders($filters)`             | Builder: paymentConfirmed + ACTIVE_STATUSES                          | date, branch, branch_ids, employee |
| `completedOrders($filters)`          | Builder: paymentConfirmed + COMPLETED_STATUSES                       | date, branch, employee             |
| `orderItems($filters)`               | Builder: order_items joined with placed orders                       | date, branch                       |
| `payments($filters)`                 | Builder: payments joined with placed orders                          | date, branch                       |
| `computeRevenue($filters)`           | float: SUM(total_amount) from revenueOrders                          | date, branch, employee             |
| `computePlacedOrderCount($filters)`  | int: COUNT from placedOrders                                         | date, branch, employee             |
| `computeRevenueOrderCount($filters)` | int: COUNT from revenueOrders                                        | date, branch, employee             |
| `applyFilters($query, $filters)`     | void: applies date_from, date_to, branch_id, branch_ids, employee_id | —                                  |

Constants: `ACTIVE_STATUSES`, `COMPLETED_STATUSES`

### 2.2 AnalyticsService (`app/Services/Analytics/AnalyticsService.php`)

The single source of truth. 19 public methods organized into sections A–S.

| Method                                          | Section | Used By                                             |
| ----------------------------------------------- | ------- | --------------------------------------------------- |
| `getSalesMetrics($filters)`                     | A       | AdminAnalyticsController, Manager/Partner Analytics |
| `getOrderMetrics($filters)`                     | B       | AdminAnalyticsController                            |
| `getCustomerMetrics($filters)`                  | C       | AdminAnalyticsController                            |
| `getTopItemsMetrics($filters)`                  | D       | AdminAnalyticsController, BranchController          |
| `getBottomItemsMetrics($filters)`               | E       | AdminAnalyticsController                            |
| `getCategoryRevenueMetrics($filters)`           | F       | AdminAnalyticsController                            |
| `getBranchMetrics($filters)`                    | G       | AdminAnalyticsController                            |
| `getStaffSalesMetrics($filters)`                | H       | BranchController::staffSales                        |
| `getDeliveryPickupMetrics($filters)`            | I       | AdminAnalyticsController                            |
| `getPaymentMethodMetrics($filters)`             | J       | AdminAnalyticsController                            |
| `getSourceMetrics($filters)`                    | —       | AdminAnalyticsController                            |
| `getPaymentStats($filters)`                     | K       | PaymentController::stats                            |
| `getFulfillmentMetrics($filters)`               | L       | AdminAnalyticsController::fulfillment (NEW)         |
| `getPromoMetrics($filters)`                     | M       | AdminAnalyticsController::promos (NEW)              |
| `getFunnelMetrics($filters)`                    | N       | AdminAnalyticsController::checkoutFunnel (NEW)      |
| `getDashboardMetrics($filters)`                 | O       | AdminDashboardController                            |
| `getBranchTodayStats($branchId)`                | P       | BranchController::index                             |
| `getBranchTodayStatsBulk($branchIds)`           | P       | AdminDashboardController (2 queries total)          |
| `getDailyReport($date)`                         | Q       | AdminReportController::daily                        |
| `getMonthlyReport($year, $month)`               | R       | AdminReportController::monthly                      |
| `getBranchDetailStats($branchId)`               | S       | BranchController::stats                             |
| `getBranchTopItems($branchId, $period, $limit)` | S       | BranchController::topItems                          |
| `getBranchRevenueChart($branchId, $period)`     | S       | BranchController::revenueChart                      |
| `getEmployeeBranchStats($branchIds)`            | S       | OrderManagementService::getBranchStats              |

### 2.3 Controllers (All Thin Wrappers Now)

| Controller               | Inline Analytics? | Delegates To                                                                         |
| ------------------------ | ----------------- | ------------------------------------------------------------------------------------ |
| AdminDashboardController | ❌ None           | `AnalyticsService::getDashboardMetrics()`, `getBranchTodayStatsBulk()` (2 queries)   |
| AdminAnalyticsController | ❌ None           | 13 AnalyticsService methods (10 existing + 3 new)                                    |
| AdminReportController    | ❌ None           | `AnalyticsService::getDailyReport()`, `getMonthlyReport()`                           |
| BranchController         | ❌ None           | `AnalyticsService::getBranchTodayStats/DetailStats/TopItems/RevenueChart/StaffSales` |
| PaymentController        | ❌ None           | `AnalyticsService::getPaymentStats()`                                                |
| OrderManagementService   | ❌ None           | `AnalyticsService::getEmployeeBranchStats()`                                         |

---

## §3 — Frontend Analytics Surface Map

### 3.1 Admin Dashboard (`app/admin/dashboard/page.tsx`)

| KPI Displayed             | Backend Endpoint                              | Client Computation? |
| ------------------------- | --------------------------------------------- | ------------------- |
| Revenue Today             | `GET /admin/dashboard` → `kpis.revenue_today` | No                  |
| Orders Today              | Same → `kpis.orders_today`                    | No                  |
| Active Orders             | Same → `kpis.active_orders`                   | No                  |
| Cancelled Today           | Same → `kpis.cancelled_today`                 | No                  |
| No-Charge Today           | Same → `kpis.no_charge_today`                 | No                  |
| Per-Branch Revenue/Orders | Same → `branches[]`                           | No                  |
| Live Orders Feed          | Same → `live_orders[]`                        | No                  |

**Stale Time**: 60s. **Risk**: Low — purely backend data.

### 3.2 Admin Analytics (`app/admin/analytics/page.tsx` ~72KB)

| Tab/Section        | Backend Endpoints                            | Client Computation? |
| ------------------ | -------------------------------------------- | ------------------- |
| Sales              | `/admin/analytics/sales`                     | No                  |
| Orders             | `/admin/analytics/orders`                    | No                  |
| Customers          | `/admin/analytics/customers`                 | No                  |
| Order Sources      | `/admin/analytics/order-sources`             | No                  |
| Top/Bottom Items   | `/admin/analytics/top-items`, `bottom-items` | No                  |
| Category Revenue   | `/admin/analytics/category-revenue`          | No                  |
| Branch Performance | `/admin/analytics/branch-performance`        | No                  |
| Delivery/Pickup    | `/admin/analytics/delivery-pickup`           | No                  |
| Payment Methods    | `/admin/analytics/payment-methods`           | No                  |

**Stale Time**: 120s. **Risk**: Low — all delegated to AnalyticsService.

### 3.3 Admin Transactions (`app/admin/transactions/page.tsx`)

| Data         | Backend Endpoint            | Client Computation? |
| ------------ | --------------------------- | ------------------- |
| Payment list | `GET /admin/payments`       | No                  |
| Stats cards  | `GET /admin/payments/stats` | No                  |

**Stale Time**: 120s. **Risk**: Low — PaymentController is unique, not duplicated.

### 3.4 Manager Dashboard (`app/staff/manager/dashboard/page.tsx`)

| KPI                  | Backend Endpoint                           | Client Computation?                 |
| -------------------- | ------------------------------------------ | ----------------------------------- |
| Today Revenue/Orders | `GET /manager/branches/{id}/stats`         | ⚠️ AOV = revenue/orders client-side |
| Weekly Revenue Chart | `GET /manager/branches/{id}/revenue-chart` | No                                  |
| Top Items            | `GET /manager/branches/{id}/top-items`     | No                                  |

**Stale Time**: 300s (5 min). **Risk**: Manager calls `BranchController::stats()` which is CONSISTENT, but `BranchController::revenueChart()` includes no_charge in revenue.

### 3.5 Manager Analytics (`app/staff/manager/analytics/page.tsx` ~65KB)

| Section          | Backend Endpoints                              | Notes                    |
| ---------------- | ---------------------------------------------- | ------------------------ |
| Sales overview   | `/admin/analytics/sales?branch_id=X`           | ✅ Uses AnalyticsService |
| Orders           | `/admin/analytics/orders?branch_id=X`          | ✅ Uses AnalyticsService |
| Top/Bottom Items | `/admin/analytics/top-items?branch_id=X`       | ✅ Uses AnalyticsService |
| Payment Methods  | `/admin/analytics/payment-methods?branch_id=X` | ✅ Uses AnalyticsService |

**Risk**: LOW — Manager analytics page correctly uses admin analytics endpoints (AnalyticsService) with branch filter. The manager **dashboard** uses BranchController though.

### 3.6 Manager Staff Sales (`app/staff/manager/staff-sales/page.tsx`)

| Data                | Backend Endpoint                                | Client Computation?               |
| ------------------- | ----------------------------------------------- | --------------------------------- |
| Per-staff breakdown | `GET /manager/branches/{id}/staff-sales?date=X` | Grand totals computed client-side |

**Risk**: MEDIUM — `staffSales()` doesn't use paymentConfirmed scope.

### 3.7 Partner Dashboard & Analytics

| Page      | Endpoints Used                                                                          | Notes                    |
| --------- | --------------------------------------------------------------------------------------- | ------------------------ |
| Dashboard | `/admin/analytics/sales`, `/admin/analytics/orders`, `/admin/orders` with branch filter | ✅ Uses AnalyticsService |
| Analytics | Same admin analytics endpoints with branch filter                                       | ✅ Uses AnalyticsService |

**Risk**: LOW — Partners correctly use AnalyticsService via admin routes.

### 3.8 Staff Dashboard & My Sales

| Page            | Endpoints Used                                                       | Notes                                     |
| --------------- | -------------------------------------------------------------------- | ----------------------------------------- |
| Staff Dashboard | `employee/orders/stats` → `OrderManagementService::getBranchStats()` | ⚠️ See §2.4 divergence notes              |
| My Sales        | Shift-based data via employee endpoints                              | ⚠️ Depends on denormalized shift counters |

---

## §4 — Divergence Registry

### §4.1 — Open Divergences

_None — all 12 original divergences resolved in the April 7 overhaul._

### §4.2 — Resolved Divergences

| #   | Was                                                                        | Resolution                                                                                              | Date          |
| --- | -------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | ------------- |
| D1  | 🔴 CRITICAL: `BranchController::index()` wrong revenue (no payment filter) | Replaced with `AnalyticsService::getBranchTodayStats()` using canonical `computeRevenue()`              | April 7, 2026 |
| D2  | 🔴 CRITICAL: Shift counters never decremented on cancel                    | Added `OrderObserver::handleCancellationSideEffects()` — decrements shift counters + deletes ShiftOrder | April 7, 2026 |
| D3  | 🟠 HIGH: AdminDashboardController inline computation                       | Replaced with `AnalyticsService::getDashboardMetrics()` + `getBranchTodayStats()`                       | April 7, 2026 |
| D4  | 🟠 HIGH: revenueChart includes no_charge in revenue                        | Replaced with `AnalyticsService::getBranchRevenueChart()` using `computeRevenue()` (excludes no_charge) | April 7, 2026 |
| D5  | 🟠 HIGH: staffSales missing paymentConfirmed scope                         | Replaced with `AnalyticsService::getStaffSalesMetrics()` using `revenueOrders()` + `noChargeOrders()`   | April 7, 2026 |
| D6  | 🟡 MEDIUM: topItems in-memory PHP aggregation                              | Replaced with `AnalyticsService::getBranchTopItems()` using DB-level aggregation via `orderItems()`     | April 7, 2026 |
| D7  | 🟡 MEDIUM: Branch performance no payment filter                            | Fixed in new `AnalyticsService::getBranchMetrics()` using canonical builders                            | April 7, 2026 |
| D8  | 🟡 MEDIUM: Daily/Monthly reports no paymentConfirmed                       | Fixed in new `AnalyticsService::getDailyReport()` / `getMonthlyReport()`                                | April 7, 2026 |
| D9  | 🟡 MEDIUM: Customer spending no payment filter                             | Fixed in new `AnalyticsService::getCustomerMetrics()`                                                   | April 7, 2026 |
| D10 | 🟡 MEDIUM: Order analytics counts all orders (no paymentConfirmed)         | Fixed in new `AnalyticsService::getOrderMetrics()` using `placedOrders()`                               | April 7, 2026 |
| D11 | 🟢 LOW: Different active status sets (preparing_orders vs active_orders)   | Fixed — both now use `AnalyticsQueryBuilder::ACTIVE_STATUSES` with `accepted` instead of `confirmed`    | April 7, 2026 |
| D12 | 🟢 LOW: Item/category includes no_charge unlike sales revenue              | By design — item analytics show all placed items. Revenue analytics excludes no_charge. Accepted.       | April 7, 2026 |

### §4.3 — Accepted Risks

| Risk                                                 | Reason                                                                                             |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| D12: Item/category analytics include no_charge items | Developer confirmed: item popularity should count all placed orders. Revenue is a separate metric. |

---

## §5 — Denormalized Counter Audit

### Shift Counters

| Counter       | Model | Update Mechanism                               | Decrement on Cancel?                                      | Reconciliation |
| ------------- | ----- | ---------------------------------------------- | --------------------------------------------------------- | -------------- |
| `total_sales` | Shift | `increment()` in `ShiftController::addOrder()` | ✅ YES — `OrderObserver::handleCancellationSideEffects()` | Auto on cancel |
| `order_count` | Shift | `increment()` in `ShiftController::addOrder()` | ✅ YES — same observer                                    | Auto on cancel |

**Status**: Fixed. When an order is cancelled, `OrderObserver` finds all ShiftOrders for that order, decrements the parent Shift's counters, and deletes the ShiftOrder records.

---

## §6 — Caching & Performance Notes

| What                                         | Cached?        | TTL               | Invalidation         |
| -------------------------------------------- | -------------- | ----------------- | -------------------- |
| AnalyticsService methods                     | ❌ No caching  | —                 | —                    |
| AdminDashboardController                     | ❌ No caching  | —                 | —                    |
| BranchController stats/topItems/revenueChart | ❌ No caching  | —                 | —                    |
| Frontend TanStack Query                      | ✅ Client-side | 60s-300s per hook | Time-based staleTime |

**Recommendation**: Add cache layer to AnalyticsService for read-heavy endpoints. Invalidate on OrderBroadcastEvent.

---

## §7 — Decision Log

| Date       | Decision                                                    | Reasoning                                                                               | Status                 |
| ---------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------- | ---------------------- |
| 2026-04-07 | Initial audit completed                                     | Full audit of all 8+ computation locations. 12 divergences identified.                  | Superseded by overhaul |
| 2026-04-07 | Revenue = placed + not cancelled + payment_status=completed | Developer confirmed. No_charge tracked separately.                                      | ✅ Locked              |
| 2026-04-07 | AOV = revenue / revenue-contributing orders only            | Excludes no_charge and cancelled from denominator. Developer confirmed.                 | ✅ Locked              |
| 2026-04-07 | Cancel = auto-refund                                        | Completed→refunded, no_charge→unchanged. Shift counters decremented.                    | ✅ Locked              |
| 2026-04-07 | `confirmed` status doesn't exist, use `accepted`            | Developer confirmed. AnalyticsQueryBuilder::ACTIVE_STATUSES uses 'accepted'.            | ✅ Locked              |
| 2026-04-07 | One Source One Pipeline architecture                        | AnalyticsQueryBuilder→AnalyticsService→Controllers. All inline analytics eliminated.    | ✅ Deployed            |
| 2026-04-07 | Old AnalyticsService deleted                                | All references migrated to `App\Services\Analytics\AnalyticsService`. Old file removed. | ✅ Done                |
| 2026-04-07 | 3 new analytics endpoints added                             | fulfillment, promos, checkout-funnel. Routes registered in admin.php.                   | ✅ Done                |

---

## §8 — Inter-Agent Contracts

| Agent         | Contract                                                                                                                         | Status            |
| ------------- | -------------------------------------------------------------------------------------------------------------------------------- | ----------------- |
| Order Auditor | "Completed order" = status IN ['completed', 'delivered']. Cancel flow must fire analytics invalidation.                          | Pending alignment |
| Menu Auditor  | Item analytics use `menu_items.id` + `menu_item_options` join. Menu restructure must preserve IDs or analytics break.            | Noted             |
| IAM Auditor   | Analytics role-scoping: admin=all, manager=branch, partner=branch(readonly), staff=self. Currently enforced at route level only. | Noted             |

---

## §9 — Changelog

| Date       | Change                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | Author            |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------- |
| 2026-04-07 | **POST-OVERHAUL AUDIT**: Fixed 4 contract mismatches (field names), N+1 in getBranchMetrics, activeOrders() now uses applyFilters() (adds branch_ids support), getEmployeeBranchStats() now uses queryBuilder for pending/preparing counts, dashboard N+1 eliminated with new getBranchTodayStatsBulk() (2 queries instead of 2N). Tests: 74 pass, 1 pre-existing IAM fail.                                                                                                                   | Analytics Auditor |
| 2026-04-07 | **OVERHAUL COMPLETE**: Created AnalyticsQueryBuilder + new AnalyticsService. Rewrote AdminDashboardController, AdminAnalyticsController, BranchController (5 methods), PaymentController::stats, OrderManagementService::getBranchStats, AdminReportController. Added auto-refund + shift counter fix in OrderObserver. Added 3 new routes (fulfillment, promos, checkout-funnel). Deleted old AnalyticsService. All 12 divergences resolved. Tests pass (74/74, 1 pre-existing IAM failure). | Analytics Auditor |
| 2026-04-07 | Created KB. Full initial audit: 12 divergences found, 16 untapped data sources identified, 0 caching, shift counter drift confirmed.                                                                                                                                                                                                                                                                                                                                                          | Analytics Auditor |
