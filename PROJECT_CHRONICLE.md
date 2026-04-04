# CediBites API — Project Chronicle

> **Purpose**: Living record of all changes, decisions, and current state of the CediBites Laravel API. Maintained by the Project Chronicle agent. Read this before starting work on any area.

> **Current Branch**: `payment-order-bug-fixes` (off `master`)

---

## System Map

### Route Files

| Route File | Domain |
|------------|--------|
| `routes/auth.php` | Registration, login, password reset |
| `routes/public.php` | Public menu, branches, categories (no auth) |
| `routes/cart.php` | Shopping cart operations |
| `routes/protected.php` | General authenticated endpoints |
| `routes/employee.php` | Employee profile, POS orders, shifts |
| `routes/manager.php` | Branch manager: employees, orders, stats |
| `routes/admin.php` | Admin CRUD, system management |
| `routes/promos.php` | Promo/discount management |
| `routes/channels.php` | WebSocket/Reverb broadcasting channels |

### Models (29)

**Core**: User, Customer, Employee, Branch, Address
**Menu**: MenuItem, MenuCategory, MenuTag, MenuAddOn, MenuItemOption, MenuItemOptionBranchPrice, MenuItemRating
**Orders**: Order, OrderItem, OrderStatusHistory, ShiftOrder, CheckoutSession
**Shopping**: Cart, CartItem
**Financial**: Payment, Promo
**Operations**: Shift, BranchOperatingHour, BranchDeliverySetting, BranchOrderType, BranchPaymentMethod
**System**: Otp, SystemSetting, ActivityLog

### Services (9)

| Service | Purpose |
|---------|---------|
| `OrderCreationService` | Order placement from checkout sessions (DB transaction, lockForUpdate) |
| `OrderManagementService` | Status changes, cancellations |
| `OrderNumberService` | Order code generation |
| `HubtelPaymentService` | Hubtel mobile money gateway |
| `HubtelSmsService` | SMS via Hubtel |
| `OTPService` | OTP generation/validation |
| `PromoResolutionService` | Promo code validation & discount |
| `AnalyticsService` | Dashboard metrics & reporting |
| `SystemSettingService` | Global config (cache-backed, 1hr TTL) |

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

*Entries are added per session, newest first.*

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

## [2026-04-04] Session: Project Chronicle Agent Setup

### Intent
Create an institutional memory system for the CediBites project — a "Project Chronicle" agent that silently observes all changes across sessions, records them, and can brief developers and other agents on the current state of any part of the system.

### Changes Made
| File | Change | Reason |
|------|--------|--------|
| `.github/agents/project-chronicle.agent.md` | Created agent definition | New agent with read/search/edit/agent/todo tools, mandatory cross-referencing, structured chronicle format |
| `.github/instructions/chronicle-reminder.instructions.md` | Created instruction file with `applyTo: "**"` | Auto-reminds all agents to ask user about updating the chronicle after every code change |
| `PROJECT_CHRONICLE.md` | Created knowledge base file | Seeded with system map (routes, models, services, architecture, integrations) |

### Cross-Repo Impact
| File (Frontend repo) | Change | Reason |
|------|--------|--------|
| `.github/agents/project-chronicle.agent.md` | Mirror of API agent definition | Same agent available in both repos |
| `.github/instructions/chronicle-reminder.instructions.md` | Mirror of API instruction | Reminder works regardless of which repo is being edited |
| `PROJECT_CHRONICLE.md` | Frontend knowledge base file | Seeded with system map (route groups, state management, API layer, utilities) |

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
