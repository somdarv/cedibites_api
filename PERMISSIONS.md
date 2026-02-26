# Permission System

## Overview

CediBites uses a flexible permission-based authorization system powered by Spatie Laravel Permission. This allows for configurable roles where permissions can be added or removed from roles as needed.

## Why Permissions Over Roles?

- **Flexibility**: Roles can be customized per deployment
- **Granular Control**: Fine-grained access control per feature
- **Scalability**: Easy to add new permissions without changing code
- **Maintainability**: Routes check permissions, not roles

## Available Permissions

### Order Management
- `view_orders` - View orders (employees, managers, admins)
- `create_orders` - Create new orders (customers, admins)
- `update_orders` - Update order status (employees, managers, admins)
- `delete_orders` - Cancel/delete orders (admins)

### Menu Management
- `view_menu` - View menu items (public access)
- `manage_menu` - Full menu CRUD (managers, admins)

### Branch Management
- `view_branches` - View branch information (public for basic info, managers/admins for details)
- `manage_branches` - Full branch CRUD (managers, admins)

### Customer Management
- `view_customers` - View customer information (managers, admins)
- `manage_customers` - Full customer CRUD (admins)

### Employee Management
- `view_employees` - View employee information (managers, admins)
- `manage_employees` - Full employee CRUD (admins only)

## Default Role Configuration

### Customer
No special permissions - authenticated access only for their own resources.

### Employee
```php
'view_orders',
'update_orders',
```

### Manager
```php
'view_orders',
'create_orders',
'update_orders',
'view_menu',
'manage_menu',
'view_branches',
'manage_branches',
'view_employees',
'view_customers',
```

### Admin
All permissions (full system access).

## Route Protection

Routes are protected using the `permission` middleware:

```php
// Single permission
Route::get('orders', [OrderController::class, 'index'])
    ->middleware('permission:view_orders');

// Multiple permissions (user must have ALL)
Route::post('orders', [OrderController::class, 'store'])
    ->middleware('permission:view_orders,create_orders');

// Group protection
Route::middleware('permission:manage_employees')->group(function () {
    Route::post('employees', [EmployeeController::class, 'store']);
    Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
});
```

## Checking Permissions in Code

### In Controllers
```php
// Check if user has permission
if ($request->user()->can('manage_employees')) {
    // User has permission
}

// Check multiple permissions (OR)
if ($request->user()->hasAnyPermission(['manage_employees', 'view_employees'])) {
    // User has at least one permission
}

// Check multiple permissions (AND)
if ($request->user()->hasAllPermissions(['manage_employees', 'manage_branches'])) {
    // User has all permissions
}
```

### In Policies
```php
public function update(User $user, Order $order): bool
{
    return $user->can('update_orders');
}
```

### In Blade Templates
```blade
@can('manage_employees')
    <button>Add Employee</button>
@endcan

@cannot('manage_employees')
    <p>You don't have permission to manage employees.</p>
@endcannot
```

## Adding New Permissions

1. **Add to Permission Enum** (`app/Enums/Permission.php`):
```php
case ManageReports = 'manage_reports';
```

2. **Add to Permission Seeder** (`database/seeders/PermissionSeeder.php`):
```php
Permission::create(['name' => 'manage_reports', 'guard_name' => 'api']);
```

3. **Assign to Roles** (`database/seeders/RoleSeeder.php`):
```php
$adminRole->givePermissionTo('manage_reports');
```

4. **Protect Routes**:
```php
Route::middleware('permission:manage_reports')->group(function () {
    Route::get('reports', [ReportController::class, 'index']);
});
```

## Modifying Role Permissions

Permissions can be modified at runtime without code changes:

```php
// Add permission to role
$role = Role::findByName('manager');
$role->givePermissionTo('manage_employees');

// Remove permission from role
$role->revokePermissionTo('manage_branches');

// Sync permissions (replace all)
$role->syncPermissions(['view_orders', 'update_orders']);
```

## Direct User Permissions

Users can also have direct permissions (not through roles):

```php
// Give permission directly to user
$user->givePermissionTo('manage_menu');

// Remove direct permission
$user->revokePermissionTo('manage_menu');

// Check if user has permission (checks both role and direct permissions)
$user->can('manage_menu'); // true
```

## Best Practices

1. **Use Permissions in Routes**: Always protect routes with permissions, not roles
2. **Granular Permissions**: Create specific permissions for specific actions
3. **Logical Grouping**: Group related permissions (e.g., all order permissions)
4. **Document Changes**: Update this file when adding new permissions
5. **Test Thoroughly**: Test permission checks with different user roles
6. **Avoid Hardcoding**: Use the Permission enum instead of strings

## Testing Permissions

```php
// In tests
$user = User::factory()->create();
$user->givePermissionTo('view_orders');

$response = $this->actingAs($user)
    ->getJson('/api/v1/employee/orders');

$response->assertOk();
```

## Troubleshooting

### Permission Denied Errors
- Check if user has the required permission: `$user->can('permission_name')`
- Verify role has the permission: `$role->hasPermissionTo('permission_name')`
- Clear permission cache: `php artisan permission:cache-reset`

### Permission Not Found
- Run migrations: `php artisan migrate`
- Seed permissions: `php artisan db:seed --class=PermissionSeeder`
- Check permission exists: `Permission::where('name', 'permission_name')->exists()`

### Cache Issues
Spatie Permission caches permissions for performance. Clear cache after changes:
```bash
php artisan permission:cache-reset
```

## API Response for Permission Errors

When a user lacks required permissions, the API returns:

```json
{
  "success": false,
  "message": "You do not have permission to perform this action.",
  "errors": null
}
```

HTTP Status: `403 Forbidden`
