# Test Plan — Laravel Online Store

## 1. Purpose

Define the testing strategy for the Laravel 13 + MySQL online store.

Testing must verify business behavior, API contracts, authorization, data integrity, inventory concurrency, payment retries, and deployment readiness.

## 2. Testing Layers

### 2.1 Unit Tests

Test isolated business rules without requiring a full HTTP request.

Examples:
- Calculate order totals from item quantities and unit prices.
- Determine whether an order status transition is allowed.
- Determine whether an order is eligible for payment retry.
- Calculate inventory quantities to reserve or release.

Unit tests should be fast and deterministic.

### 2.2 Feature Tests

Test application behavior through Laravel's HTTP or application interfaces.

Examples:
- Register a customer.
- Log in and log out.
- Retrieve the public product catalog.
- Add an item to a cart.
- Create an order through checkout.
- Reject a customer attempting to view another customer's order.
- Reject an unauthorized admin request.

Use Laravel's testing facilities, such as HTTP test methods and database assertions.

### 2.3 Database Integration Tests

Verify that application behavior works correctly with MySQL.

Examples:
- Foreign-key constraints prevent invalid relationships.
- Unique constraints reject duplicate idempotency keys within their defined scope.
- Transactions roll back incomplete changes.
- Product-row locking protects inventory under concurrent checkout.
- Inventory reservations are released exactly once.

Concurrency tests must use a database engine and configuration that support the actual locking behavior. SQLite tests alone do not prove MySQL concurrency safety.

## 3. Test Environment

- Use a dedicated test database, never production data.
- Use the same MySQL major version as the intended deployment where practical.
- Keep test credentials and environment variables separate from development and production.
- Reset or isolate database state between tests.
- Use factories and seeders to create controlled test data.
- Fake or simulate external integrations rather than charging real payment methods.
- Ensure test execution is repeatable.

## 4. Authentication Tests

| ID | Scenario | Expected result |
|---|---|---|
| AUTH-01 | Register with valid details | Customer account is created |
| AUTH-02 | Register with an existing email | Validation error; no duplicate account |
| AUTH-03 | Register with an invalid email or password | Validation errors |
| AUTH-04 | Submit an admin role during public registration | Account is not granted admin privileges |
| AUTH-05 | Log in with valid credentials | Session is authenticated |
| AUTH-06 | Log in with invalid credentials | Authentication fails |
| AUTH-07 | Log out | Session is no longer authenticated |
| AUTH-08 | Guest calls a protected endpoint | Request is rejected |

## 5. Catalog and Cart Tests

| ID | Scenario | Expected result |
|---|---|---|
| CART-01 | List active products | Only active products are publicly listed |
| CART-02 | Request an inactive product | It is not returned as available |
| CART-03 | Add an active product with valid quantity | Cart item is created or updated according to the contract |
| CART-04 | Add quantity zero or a negative quantity | Request is rejected |
| CART-05 | Add an inactive product | Request is rejected |
| CART-06 | Submit a manipulated product price | Server-side price is used |
| CART-07 | Update another customer's cart item | Access is denied |
| CART-08 | Remove another customer's cart item | Access is denied |
| CART-09 | Checkout after stock changes | Current stock is revalidated |

## 6. Checkout and Order Tests

| ID | Scenario | Expected result |
|---|---|---|
| ORDER-01 | Checkout with a nonempty valid cart | Order and item snapshots are created |
| ORDER-02 | Checkout with an empty cart | No order is created |
| ORDER-03 | Product price changes after being added to the cart | Checkout follows the documented pricing policy and uses server-side data |
| ORDER-04 | Product has insufficient stock | Checkout fails without partial order or stock changes |
| ORDER-05 | Checkout contains multiple products | Subtotal and total match the server-side calculation |
| ORDER-06 | Retrieve an owned order | Order details are returned |
| ORDER-07 | Retrieve another customer's order | Access is denied |
| ORDER-08 | Repeat checkout with the same idempotency key | No duplicate order is created |
| ORDER-09 | Submit the same key concurrently | At most one logical checkout operation is created |
| ORDER-10 | An exception occurs during order creation | The transaction rolls back incomplete database changes |

## 7. Inventory Concurrency Tests

### INV-01 — Competing purchases for the last unit

Given:
- A product has one unit in stock.
- Two different customers attempt to purchase one unit concurrently.

When both checkout operations execute, then:
- At most one checkout reserves the unit.
- The other checkout receives a controlled insufficient-stock or conflict response.
- Stock never becomes negative.
- No invalid paid or confirmed order is created for unreserved stock.

### INV-02 — Reservation release

Given an unpaid order has reserved two units.

When its reservation expires or it is cancelled:
- Stock increases by exactly two units.
- The release operation is recorded or otherwise made durable and unambiguous.

### INV-03 — Repeated release

Given an unpaid order's reservation has already been released.

When the cancellation or expiry operation runs again:
- Stock does not increase a second time.
- The operation remains safe to retry.

### INV-04 — Successful payment versus expiry

Given a payment success and an expiration process could occur near the same time:
- Only a valid, consistent state transition may win.
- Stock must not be released for an order that has successfully completed payment.
- A late payment result for an expired or cancelled order follows an explicit reconciliation policy rather than silently confirming the order.

## 8. Simulated Payment Tests

| ID | Scenario | Expected result |
|---|---|---|
| PAY-01 | Simulated payment succeeds | Payment is recorded as succeeded; order becomes confirmed |
| PAY-02 | Simulated payment fails | Failed payment attempt is recorded; order remains pending |
| PAY-03 | Retry a failed payment on an eligible order | A new attempt is recorded without duplicating the order or reservation |
| PAY-04 | Repeat the same payment-attempt request with the same idempotency key | The same logical attempt result is returned; no duplicate effect |
| PAY-05 | Retry after reservation expiry | Retry is rejected unless a defined recovery workflow safely reacquires inventory |
| PAY-06 | Cancel an unpaid order | Reserved stock is released once |
| PAY-07 | Attempt to cancel or expire a successfully paid order through the unpaid-order workflow | Stock is not released |
| PAY-08 | Process the same success result more than once | No duplicate successful payment effect |
| PAY-09 | Payment attempt belongs to another customer's order | Access is denied |

## 9. Administration and Authorization Tests

- Guests cannot access admin endpoints.
- Customers cannot create or update products or categories.
- Customers cannot change order status through administrative endpoints.
- Administrators can perform authorized catalog-management operations.
- Invalid order-state transitions are rejected.
- A customer cannot access another customer's cart, order, or payment retry.
- Authorization is checked on the backend, not only in the interface.

## 10. API Contract Tests

Verify:
- Correct HTTP status codes.
- Consistent JSON success and error formats.
- Validation errors include the appropriate field details.
- Pagination metadata matches the actual result set.
- Missing resources return the documented response.
- Conflicts and invalid state transitions return the documented response.
- Production responses do not reveal secrets or stack traces.

## 11. Test Execution Strategy

Run tests in this order during development:

1. Unit tests for individual business rules.
2. Feature tests for endpoints and use cases.
3. Database integration tests for constraints and transactions.
4. Concurrency and idempotency tests for checkout and payment.
5. Full regression suite after significant changes.
6. Deployment smoke tests in the target environment.

The exact commands will be documented and verified when the Laravel project is initialized.

## 12. Definition of Done

A feature is ready when:
- Relevant tests pass.
- Authorization and ownership rules are verified.
- Database state remains consistent after success and failure.
- Checkout, payment retry, and reservation release are safe to repeat.
- No critical known defect remains.
- Test results can be reproduced from a clean environment.