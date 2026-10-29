# API Specification
## Laravel Online Store

**Version:** v1  
**Base path:** `/api/v1`  
**Format:** JSON  
**Authentication:** Laravel-supported session or token strategy, to be finalized before implementation

## 1. General Rules

- Use resource-oriented REST endpoints.
- Validate input on the server.
- Never trust prices or totals supplied by the client.
- Protect customer-owned resources from access by other customers.
- Require administrator authorization for catalog and order-management operations.
- Use pagination for product and order lists.
- Return consistent JSON success and error structures.
- Do not expose stack traces, secrets, or internal exception details in production.
- Use database transactions and concurrency controls for checkout.

## 2. Catalog

### GET /products

List active products with pagination.

Supported query parameters:
- `page`
- `per_page`
- `category_id`
- `search`

Invalid pagination values must be rejected or safely normalized according to the final validation rules.

### GET /products/{product}

Return one active product. Inactive products must not be exposed through the public catalog.

### GET /categories

Return categories available for public browsing.

## 3. Authentication

### POST /register

Register a customer with validated name, email, and password.

The public registration endpoint must not allow users to assign themselves the administrator role.

### POST /login

Authenticate a customer and establish the configured authentication mechanism.

### POST /logout

End the authenticated session or revoke the relevant token, according to the selected authentication strategy.

## 4. Cart

All cart endpoints require authentication in the initial contract.

### GET /cart

Return the current customer's cart and its items.

### POST /cart/items

Accept `product_id` and `quantity`.

Validation:
- Product must exist and be active.
- Quantity must be a positive integer.
- The server must enforce inventory rules.
- Repeated additions of the same product should update the existing cart item.

### PATCH /cart/items/{item}

Accept a new positive quantity.

The cart item must belong to the authenticated customer's cart.

### DELETE /cart/items/{item}

Remove an item only if it belongs to the authenticated customer's cart.

## 5. Checkout

### POST /checkout

Authentication is required.

The server must:
1. Load the authenticated customer's cart.
2. Reject an empty cart.
3. Verify product availability and current prices.
4. Validate stock using appropriate transaction and locking rules.
5. Calculate monetary amounts on the server.
6. Create the order and order-item snapshots.
7. Coordinate inventory updates and simulated payment processing.
8. Prevent duplicate processing of the same logical checkout operation.
9. Return the resulting order and payment state.

The exact transaction boundary and behavior for failed payments must be finalized before implementation. A real external payment provider must not be treated as if its side effects can be rolled back by a MySQL transaction.

## 6. Customer Orders

### GET /orders

Return only orders belonging to the authenticated customer, with pagination.

### GET /orders/{order}

Return order details only when the order belongs to the authenticated customer or the caller has the appropriate administrator permission.

## 7. Administration

All `/admin` endpoints require administrator authorization.

### POST /admin/products

Create a product with validated name, slug, category, price, stock quantity, description, and status.

### PATCH /admin/products/{product}

Update permitted product fields.

### DELETE /admin/products/{product}

Deactivate products that should no longer be sold. Physical deletion must respect historical references.

### POST /admin/categories

Create a category with a unique slug.

### PATCH /admin/categories/{category}

Update permitted category fields.

### PATCH /admin/orders/{order}/status

Accept a requested status change only when the transition is allowed by the business rules.

## 8. Error Responses

Use a consistent JSON structure, for example:

{
  "message": "The request could not be completed.",
  "errors": {
    "quantity": ["The quantity must be a positive integer."]
  }
}

Common status codes:
- `200 OK`: successful retrieval or update.
- `201 Created`: resource created.
- `204 No Content`: successful deletion with no response body.
- `401 Unauthorized`: authentication required or failed.
- `403 Forbidden`: authenticated caller lacks permission.
- `404 Not Found`: resource not found or deliberately concealed.
- `409 Conflict`: conflict with current application state.
- `422 Unprocessable Content`: validation or business-rule failure.
- `429 Too Many Requests`: rate limit exceeded.
- `500 Internal Server Error`: unexpected server failure.

## 9. Open Decisions

Before implementation, finalize:
- Session-based authentication versus token authentication.
- Guest-cart support.
- Exact order-status transitions.
- Simulated-payment success and failure behavior.
- Checkout idempotency mechanism.
- Standard response envelope and pagination format.