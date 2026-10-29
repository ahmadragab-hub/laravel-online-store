# Acceptance Criteria — Laravel Online Store

## 1. Purpose

Define the observable conditions required to accept each feature of the Laravel 13 + MySQL online store.

A feature is complete only when its acceptance criteria pass, relevant automated tests pass, and no known critical security or data-integrity issue remains.

## 2. Product Catalog

### AC-CAT-01 — List products
- The API returns active products in a paginated response.
- Each product includes its ID, name, slug, description, price, stock availability, and category information as specified by the API contract.
- Inactive products are not exposed through public catalog endpoints.
- The response follows the agreed JSON format.

### AC-CAT-02 — Filter and search
- Customers can filter products by category.
- Customers can search by supported product fields.
- Invalid pagination or filter input is handled safely.
- Filters are applied in the database rather than loading the entire catalog into PHP.

### AC-CAT-03 — View product details
- An active product can be retrieved by its identifier or supported route key.
- A missing or inactive product is not returned as an available public product.

## 3. Authentication and Accounts

### AC-AUTH-01 — Register
- A visitor can register with valid required information.
- Duplicate email addresses are rejected.
- Passwords are securely hashed before storage.
- Public registration always assigns the customer role.
- The request cannot grant administrator privileges by submitting a role field.

### AC-AUTH-02 — Log in and log out
- Valid credentials establish an authenticated session.
- Invalid credentials do not authenticate the user.
- Logging out ends the authenticated session.
- Browser state-changing requests use the required CSRF protections.

### AC-AUTH-03 — Protected endpoints
- Guests cannot access protected cart, checkout, or order endpoints.
- Unauthenticated requests receive the appropriate API response.
- Authentication state is checked by the backend.

## 4. Cart

### AC-CART-01 — Add an item
- An authenticated customer can add an active, purchasable product.
- Quantity must be a positive integer.
- Adding an existing product to the cart follows the defined quantity-update behavior.
- A client-supplied price is ignored.
- Inactive products and invalid quantities are rejected.

### AC-CART-02 — Update and remove items
- Customers can change the quantity of their own cart items.
- Customers can remove their own cart items.
- A customer cannot change or remove another customer's cart item.
- A missing cart item returns an appropriate response.

### AC-CART-03 — Cart totals
- Cart totals are calculated using current server-side product prices.
- The API does not trust totals calculated by the frontend.
- Stock availability is rechecked during checkout, even if the cart previously showed sufficient stock.

## 5. Checkout and Orders

### AC-ORDER-01 — Create an order
- Only an authenticated customer can checkout.
- An empty cart cannot produce an order.
- Product prices and totals are calculated on the server.
- The order stores a snapshot of product names, quantities, and unit prices.
- Later product price changes do not alter historical order-item prices.
- Order creation and inventory reservation are protected against partial database updates.

### AC-ORDER-02 — Inventory concurrency
- Concurrent checkouts cannot sell more units than are available.
- Inventory does not become negative.
- Relevant product rows are locked or an equivalent safe concurrency-control strategy is used.
- If checkout fails before committing, incomplete order and inventory changes are rolled back.

### AC-ORDER-03 — Idempotent checkout
- Repeating checkout with the same customer-scoped idempotency key does not create a duplicate order.
- The original result can be retrieved safely on a repeated request.
- A different checkout operation uses a different key.
- Idempotency records and order creation remain consistent under concurrent requests.

### AC-ORDER-04 — Order access
- Customers can list their own orders and view their own order details.
- Customers cannot access another customer's order by changing an ID.
- Administrators can access orders according to the RBAC rules.
- Order status transitions are validated on the backend.

## 6. Simulated Payments

### AC-PAY-01 — Successful payment
- A successful simulated payment is recorded in the payments table.
- The order transitions to the confirmed state according to the defined workflow.
- The reserved stock remains deducted.
- Repeated processing does not create duplicate successful payment effects.

### AC-PAY-02 — Failed payment
- A failed payment attempt is recorded with a failed status.
- The order is retained in the pending state.
- The inventory reservation remains in place until retry succeeds, cancellation occurs, or the reservation expires.
- A failed attempt does not delete the order.

### AC-PAY-03 — Retry payment
- An eligible pending order can be retried.
- Each actual attempt is recorded separately.
- A retry does not create a duplicate order or reserve the same inventory again.
- Each attempt uses an appropriate idempotency key.
- An expired or cancelled order cannot be retried as though its reservation were still active.

### AC-PAY-04 — Release inventory
- Cancelling or expiring an unpaid order releases its reserved stock.
- Inventory is restored exactly once.
- Repeated cancellation, expiration, or retry processing cannot restore the same stock multiple times.
- A successful payment prevents the normal unpaid-order expiration process from releasing the reservation.

## 7. Administration

### AC-ADMIN-01 — Product management
- Only administrators can create and update products.
- Product prices, stock quantities, category assignments, and status are validated.
- Products referenced by historical orders are deactivated rather than destructively deleted when deletion would violate order-history integrity.

### AC-ADMIN-02 — Category management
- Only administrators can create and update categories.
- Category slugs remain unique.
- Categories referenced by products cannot be deleted if the database relationship forbids it.

### AC-ADMIN-03 — Order management
- Only administrators can use administrative order-management endpoints.
- Only permitted status transitions are accepted.
- Invalid transitions are rejected without changing the order.
- Inventory and payment records remain consistent with order changes.

## 8. Error Handling and API Consistency

### AC-API-01 — Validation errors
- Invalid input returns a consistent validation response.
- Field-level errors are available where applicable.
- Invalid input does not partially modify protected data.

### AC-API-02 — Resource and state errors
- Missing resources return an appropriate not-found response.
- Unauthorized access is rejected.
- Invalid state transitions and conflicting operations return the appropriate error response.
- Production responses do not expose secrets, stack traces, or internal exception details.

### AC-API-03 — Pagination
- List endpoints use the documented pagination format.
- Page sizes are bounded to prevent excessive requests.
- Invalid pagination values are handled consistently.

## 9. Automated Testing

### AC-TEST-01 — Feature coverage
Automated tests cover:
- Public catalog behavior.
- Registration, login, and logout.
- Cart operations and ownership.
- Checkout calculations and order creation.
- Inventory concurrency and reservation release.
- Payment success, failure, and retry.
- Idempotency.
- Customer/admin authorization.
- Validation errors and invalid order-state transitions.

### AC-TEST-02 — Regression safety
- The test suite can run against an isolated test database.
- Tests do not depend on production data.
- The suite is rerun after changes to checkout, payment, inventory, or authorization behavior.

## 10. Operational Readiness

### AC-OPS-01 — Configuration and deployment
- Environment-specific settings and secrets are not committed to source control.
- Database migrations can be applied to a clean database.
- The application can be configured for the intended deployment environment.
- A documented procedure exists for backups and recovery.

### AC-OPS-02 — Logging and failures
- Relevant operational failures are logged without exposing passwords, secrets, or sensitive payment data.
- The application reports errors without leaking internal details.
- Critical checkout and payment failures can be investigated from appropriate logs.

## 11. Definition of Done

A feature is accepted when:
1. Its applicable acceptance criteria pass.
2. Relevant automated tests pass.
3. Authorization and data-integrity checks pass.
4. Database changes and API documentation are updated.
5. No known critical defect remains.
6. The result has been reviewed and verified in the intended environment.