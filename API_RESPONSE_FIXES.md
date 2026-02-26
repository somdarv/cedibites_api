# API Response Structure Fixes

## Summary
Fixed inconsistent API response structures across all endpoints to ensure frontend integration works correctly.

## Changes Made

### 1. BranchController
- **Issue**: `index()` was using `paginate()` and returning raw pagination structure
- **Fix**: Changed to `get()` and wrapped with `response()->success()` to return `{ data: [...] }`
- **Endpoint**: `GET /api/v1/branches`
- **Response**: `{ data: Branch[] }`

### 2. CartController
- **Issue**: Multiple inconsistent response structures
  - `index()` returned `{ cart, subtotal, total_items }` instead of just the cart
  - `store()` returned `{ cart_item, cart }` instead of just the cart
  - `update()` returned just the cart item instead of the full cart
  - `destroy()` returned 204 No Content instead of updated cart
  
- **Fixes**:
  - `index()`: Now returns `{ data: Cart }` with calculated subtotal
  - `store()`: Now returns `{ data: Cart }` with all items loaded
  - `update()`: Now returns `{ data: Cart }` with updated totals
  - `destroy()`: Now returns `{ data: Cart | null }` with updated cart or null if empty

### 3. OrderController
- **Issue**: Missing `OrderCollection` import
- **Fix**: Added import statement
- **Note**: Already using correct pagination structure via `OrderCollection`

## Response Structure Standards

All API endpoints now follow these patterns:

### Single Resource
```json
{
  "data": {
    "id": 1,
    "name": "...",
    ...
  }
}
```

### Collection (Non-Paginated)
```json
{
  "data": [
    { "id": 1, ... },
    { "id": 2, ... }
  ]
}
```

### Collection (Paginated)
```json
{
  "data": [
    { "id": 1, ... },
    { "id": 2, ... }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 75
  }
}
```

### Empty/Null Response
```json
{
  "data": null
}
```

## Frontend Integration

All frontend hooks now correctly access `response.data` or `response?.data`:

- `useBranches()`: `branchesData?.data`
- `useMenu()`: `menuData?.data`
- `useCart()`: `cartData?.data`
- `useOrders()`: `ordersData?.data` (with meta and links for pagination)

## Testing

Test all endpoints return correct structure:
```bash
# Branches
curl https://cedibites_api.test/api/v1/branches | jq 'keys'
# Should return: ["data"]

# Menu Items
curl https://cedibites_api.test/api/v1/menu-items | jq 'keys'
# Should return: ["data"]

# Cart (requires auth)
curl -H "Authorization: Bearer TOKEN" https://cedibites_api.test/api/v1/cart | jq 'keys'
# Should return: ["data"]
```
