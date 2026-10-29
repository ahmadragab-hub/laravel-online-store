# Project progress — Laravel online store API

Last updated: 2026-10-16

## Completed backend phases

| Phase | Status | Notes |
|-------|--------|-------|
| Auth (Sanctum), roles | Done | Customer + admin middleware |
| Catalog (products, categories) | Done | Public read APIs |
| Cart & checkout | Done | Idempotent checkout, stock reservation |
| Orders (customer) | Done | List/show own orders |
| Admin catalog CRUD | Done | Products, categories |
| Admin inventory adjustments | Done | Audited stock changes |
| Admin order lifecycle | Done | Cancel, complete; no manual processing |
| **Simulated payments** | **Done** | Customer route, idempotency, expiry handling |
| **Caching & queues** | **Done** | Catalog cache + invalidation; expiry job + schedule |

## Phase: Simulated payments

- **Endpoint:** `POST /api/v1/orders/{order}/simulated-payment` (authenticated customer, `local`/`testing` only)
- **Headers/body:** `Idempotency-Key` (min 16 chars), JSON `outcome`: `success` \| `failure`
- **Behavior:**
  - Success → order `processing`, reservation `consumed`, payment `succeeded`
  - Failure → order stays `pending`/`reserved`, payment `failed`
  - Same idempotency key → replays original payment (HTTP 200)
  - Expired reservation → expires order, restores stock, failed payment (HTTP 409)
  - Idempotency key bound to another order → HTTP 409
- **Services:** `SimulatedPaymentService`, `OrderLifecycleService` (exclusive path to `processing`)

## Phase: Caching & queues

- **`CatalogCacheService`:** versioned cache keys; `invalidate()` bumps version (no key scanning)
- **Cached reads:** product index (via `ProductCatalogService`), product show, category index
- **Invalidation:** admin product/category CRUD, inventory adjustments, checkout, reservation release (cancel/expire)
- **`ExpireOrderReservationsJob`:** queued expiry with `$tries = 3`, backoff `[10, 30, 60]`
- **Schedule:** `routes/console.php` runs job every minute with `withoutOverlapping()`
- **Artisan:** `php artisan orders:expire-reservations` dispatches job synchronously for ops/manual runs

## Verification (2026-10-16)

```text
php artisan test
Tests: 61 passed (213 assertions)
Duration: ~5s
```

## Not in repo

- `AGENTS.md` was not present in the workspace at implementation time.

## Recommended next steps

1. Frontend or API docs (OpenAPI) for checkout → pay → admin complete flow
2. Production payment provider integration (replace simulated endpoint)
3. Run queue worker + scheduler in deployment (`php artisan queue:work`, cron for `schedule:run`)
4. Redis cache/queue drivers for production if not using database drivers
