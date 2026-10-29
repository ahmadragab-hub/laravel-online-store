# RBAC — Role-Based Access Control

## 1. Purpose

Define the permissions for guests, customers, and administrators in the Laravel 13 online store.

Authorization must be enforced by the backend. Hiding a button in the UI is not sufficient protection.

## 2. Roles

### Guest
An unauthenticated visitor.

Can:
- Browse active products.
- Browse categories.
- Register as a customer.
- Log in.

Cannot:
- Access authenticated cart or checkout endpoints.
- View customer orders.
- Access administration endpoints.

### Customer
An authenticated customer.

Can:
- Browse the catalog.
- Manage their own cart.
- Checkout.
- View their own orders and order details.
- Retry payment for an eligible pending order.

Cannot:
- View another customer's cart or orders.
- Create or update products or categories.
- Change order status through administrative endpoints.
- Assign themselves an administrator role.

### Admin
An authenticated administrator.

Can:
- Create and update products.
- Activate or deactivate products.
- Create and update categories.
- Manage inventory.
- View orders across customers.
- Update order status through valid state transitions.

Administrative actions must be authorized on the server and recorded in logs where appropriate.

## 3. Permission matrix

| Action | Guest | Customer | Admin |
|---|---|---|---|
| Browse active catalog | Yes | Yes | Yes |
| Register / log in | Yes | Yes | Yes |
| Manage own cart | No | Yes | Yes, if explicitly supported |
| Checkout | No | Yes | Only if explicitly supported |
| View own orders | No | Yes | Yes |
| View another customer's order | No | No | Yes |
| Manage products/categories | No | No | Yes |
| Manage inventory | No | No | Yes |
| Change order status | No | No | Yes, within allowed transitions |

Admin access to customer-facing cart or checkout operations is not automatically required just because the user is an admin. Implement those capabilities only if the business requires them.

## 4. Laravel authorization approach

### Authentication
Use Laravel's authentication mechanisms and `auth` middleware to identify the signed-in user.

### Role authorization
Protect administrative routes with an admin middleware or an equivalent authorization policy.

### Resource ownership
Use policies or explicit authorization checks to verify that a customer owns the requested cart, order, or other private resource.

### Validation
Use Form Requests to validate incoming data and, where appropriate, authorize the request. Validation and authorization solve different problems.

### Registration protection
Never trust a `role` value supplied by a public registration request. New public registrations must receive the customer role on the server.

## 5. Security rules

- Authentication does not automatically grant permission.
- Customers may access only their own private resources.
- Never rely on an unguessable ID as the only ownership protection.
- Reject unauthorized administrative requests even if they are sent directly rather than through the UI.
- Validate permitted order-state transitions on the server.
- Do not allow a customer to retry payment on another customer's order.
- Ensure a payment retry cannot bypass reservation expiry or duplicate-payment protections.
- Use consistent responses for forbidden or unavailable resources according to the API contract.

## 6. Required authorization tests

1. A guest cannot access protected cart, checkout, or order endpoints.
2. A customer can access their own order.
3. A customer cannot access another customer's order by changing the order ID.
4. A customer cannot create or modify products through admin endpoints.
5. Public registration cannot create an admin account by submitting `role: "admin"`.
6. An admin can perform permitted catalog-management actions.
7. Invalid order-status transitions are rejected.
8. A customer cannot retry payment for another customer's order.
9. Repeated cancellation or expiry processing cannot restore inventory more than once.

## 7. Acceptance standard

Authorization is complete when the server consistently enforces the permission matrix and automated tests verify both allowed and denied actions.