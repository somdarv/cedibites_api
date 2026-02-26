# CediBites API Endpoints

## Authentication

### Customer Authentication
- `POST /api/v1/auth/send-otp` - Send OTP to phone
- `POST /api/v1/auth/verify-otp` - Verify OTP and get token
- `POST /api/v1/auth/register` - Register new customer
- `GET /api/v1/auth/user` - Get authenticated user (requires auth)
- `POST /api/v1/auth/logout` - Logout (requires auth)

### Employee Authentication
- `POST /api/v1/employee/login` - Employee login with phone/password
- `POST /api/v1/employee/logout` - Employee logout (requires auth)

## Public Endpoints

### Branches
- `GET /api/v1/branches` - List all branches
- `GET /api/v1/branches/{id}` - Get branch details

### Menu
- `GET /api/v1/menu-items` - List all menu items
- `GET /api/v1/menu-items/{id}` - Get menu item details

## Customer Endpoints (Requires Authentication)

### Cart Management
- `GET /api/v1/cart` - Get customer's cart
- `POST /api/v1/cart/items` - Add item to cart
- `PATCH /api/v1/cart/items/{id}` - Update cart item
- `DELETE /api/v1/cart/items/{id}` - Remove cart item
- `DELETE /api/v1/cart/clear` - Clear entire cart

### Orders
- `GET /api/v1/orders` - List customer orders
- `POST /api/v1/orders` - Create new order
- `GET /api/v1/orders/{id}` - Get order details
- `PATCH /api/v1/orders/{id}` - Update order
- `DELETE /api/v1/orders/{id}` - Cancel order

### Notifications
- `GET /api/v1/notifications` - List notifications
- `GET /api/v1/notifications/unread-count` - Get unread count
- `POST /api/v1/notifications/mark-all-read` - Mark all as read
- `PATCH /api/v1/notifications/{id}/read` - Mark notification as read
- `DELETE /api/v1/notifications/{id}` - Delete notification

## Employee Endpoints (Requires: employee, manager, or admin role)

### Order Management
- `GET /api/v1/employee/orders` - List branch orders
  - Query params: `status`, `order_type`, `date_from`, `date_to`, `per_page`
- `GET /api/v1/employee/orders/stats` - Get branch statistics
- `GET /api/v1/employee/orders/pending` - Get pending orders
- `PATCH /api/v1/employee/orders/{id}/status` - Update order status
  - Body: `{ "status": "preparing", "notes": "Optional notes" }`

## Manager Endpoints (Requires: manager or admin role)

### Branch Management
- `GET /api/v1/manager/branches/{id}/employees` - List branch employees
- `GET /api/v1/manager/branches/{id}/orders` - List branch orders
  - Query params: `status`, `date_from`, `date_to`, `per_page`
- `GET /api/v1/manager/branches/{id}/stats` - Get branch statistics

## Admin Endpoints (Requires: admin role)

### Employee Management
- `GET /api/v1/admin/employees` - List all employees
  - Query params: `branch_id`, `status`, `search`, `per_page`
- `POST /api/v1/admin/employees` - Create new employee
  - Body: `{ "name", "email", "phone", "password", "branch_id", "role", "hire_date", "status" }`
- `GET /api/v1/admin/employees/{id}` - Get employee details
- `PATCH /api/v1/admin/employees/{id}` - Update employee
- `DELETE /api/v1/admin/employees/{id}` - Deactivate employee

### Customer Management
- `GET /api/v1/admin/customers` - List all customers
  - Query params: `is_guest`, `per_page`
- `POST /api/v1/admin/customers` - Create customer
- `GET /api/v1/admin/customers/{id}` - Get customer details
- `PATCH /api/v1/admin/customers/{id}` - Update customer
- `DELETE /api/v1/admin/customers/{id}` - Delete customer
- `GET /api/v1/admin/customers/{id}/orders` - Get customer orders

### Branch Management
- `POST /api/v1/admin/branches` - Create branch
- `PATCH /api/v1/admin/branches/{id}` - Update branch
- `DELETE /api/v1/admin/branches/{id}` - Delete branch
- `GET /api/v1/admin/branches/{id}/employees` - List branch employees
- `GET /api/v1/admin/branches/{id}/orders` - List branch orders
- `GET /api/v1/admin/branches/{id}/stats` - Get branch statistics

### Menu Category Management
- `GET /api/v1/admin/menu-categories` - List all categories
  - Query params: `is_active`
- `POST /api/v1/admin/menu-categories` - Create category
  - Body: `{ "name", "description", "display_order", "is_active" }`
- `GET /api/v1/admin/menu-categories/{id}` - Get category details
- `PATCH /api/v1/admin/menu-categories/{id}` - Update category
- `DELETE /api/v1/admin/menu-categories/{id}` - Delete category

### Menu Item Management
- `POST /api/v1/admin/menu-items` - Create menu item
- `PATCH /api/v1/admin/menu-items/{id}` - Update menu item
- `DELETE /api/v1/admin/menu-items/{id}` - Delete menu item

### Payment Management
- `GET /api/v1/admin/payments` - List all payments
  - Query params: `payment_status`, `payment_method`, `date_from`, `date_to`, `per_page`
- `GET /api/v1/admin/payments/{id}` - Get payment details
- `POST /api/v1/admin/payments/{id}/refund` - Process refund

### Analytics
- `GET /api/v1/admin/analytics/sales` - Get sales analytics
  - Query params: `date_from`, `date_to`, `branch_id`
  - Returns: total_sales, total_orders, average_order_value, sales_by_day, sales_by_type
- `GET /api/v1/admin/analytics/orders` - Get order analytics
  - Query params: `date_from`, `date_to`, `branch_id`
  - Returns: orders_by_status, orders_by_hour, average_prep_time, total_orders
- `GET /api/v1/admin/analytics/customers` - Get customer analytics
  - Query params: `date_from`, `date_to`
  - Returns: total_customers, new_customers_30_days, top_customers_by_orders, top_customers_by_spending

### Reports
- `GET /api/v1/admin/reports/daily` - Get daily report
  - Query params: `date` (optional, defaults to today)
  - Returns: date, total_orders, completed_orders, cancelled_orders, total_revenue, average_order_value, orders_by_type, orders_by_status
- `GET /api/v1/admin/reports/monthly` - Get monthly report
  - Query params: `year`, `month` (optional, defaults to current month)
  - Returns: year, month, total_orders, completed_orders, cancelled_orders, total_revenue, average_order_value, daily_breakdown

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }
}
```

### Paginated Response
```json
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": [...],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 75
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

## Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {token}
```

## Roles & Permissions

The API uses a flexible permission-based authorization system. Roles are configurable and can be assigned different permissions.

### Available Permissions

**Order Permissions:**
- `view_orders` - View orders
- `create_orders` - Create new orders
- `update_orders` - Update order status and details
- `delete_orders` - Cancel/delete orders

**Menu Permissions:**
- `view_menu` - View menu items (public)
- `manage_menu` - Create, update, delete menu items and categories

**Branch Permissions:**
- `view_branches` - View branch information
- `manage_branches` - Create, update, delete branches

**Customer Permissions:**
- `view_customers` - View customer information
- `manage_customers` - Create, update, delete customers

**Employee Permissions:**
- `view_employees` - View employee information
- `manage_employees` - Create, update, delete employees

### Default Role Permissions

**Customer Role:**
- Can manage their own cart, orders, and profile
- No special permissions required (authenticated access only)

**Employee Role:**
- `view_orders` - Can view and manage orders for their branch
- `update_orders` - Can update order status

**Manager Role:**
- All Employee permissions
- `view_branches` - Can view branch details and statistics
- `view_employees` - Can view branch employees
- `view_customers` - Can view customer information

**Admin Role:**
- All permissions (full system access)
- Can manage employees, customers, branches, menu, payments
- Access to analytics and reports

### Permission-Based Endpoints

Endpoints are protected by specific permissions rather than roles, allowing for flexible role configuration:

- **Employee endpoints** (`/api/v1/employee/*`) - Require `view_orders` permission
- **Manager endpoints** (`/api/v1/manager/*`) - Require `view_branches` permission
- **Admin endpoints** (`/api/v1/admin/*`) - Require specific permissions per resource

## Order Status Flow

1. `pending` - Order created, awaiting confirmation
2. `confirmed` - Order confirmed by restaurant
3. `preparing` - Order is being prepared
4. `ready` / `ready_for_pickup` - Order ready for pickup
5. `out_for_delivery` - Order out for delivery (delivery orders only)
6. `delivered` / `completed` - Order completed
7. `cancelled` - Order cancelled

## Test Credentials

### Admin User
- Email: admin@cedibites.com
- Phone: +233500000000
- Password: password123
- Employee No: EMP00001
