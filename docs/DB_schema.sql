# Database Schema — Laravel Online Store

**Database:** MySQL  
**Currency assumption:** KWD (three decimal places)  
**Version:** Draft v1

## 1. Users

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'customer',
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_users_role
        CHECK (role IN ('customer', 'admin'))
) ENGINE=InnoDB;
```

## 2. Categories

```sql
CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB;
```

## 3. Products

```sql
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT NULL,
    price DECIMAL(12,3) NOT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_products_price
        CHECK (price >= 0),

    CONSTRAINT chk_products_status
        CHECK (status IN ('active', 'inactive')),

    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE RESTRICT,

    INDEX idx_products_category_status (category_id, status),
    INDEX idx_products_status (status)
) ENGINE=InnoDB;
```

## 4. Carts

One active cart per authenticated user in this initial design.

```sql
CREATE TABLE carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_carts_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
```

## 5. Cart Items

```sql
CREATE TABLE cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_cart_items_quantity
        CHECK (quantity > 0),

    CONSTRAINT fk_cart_items_cart
        FOREIGN KEY (cart_id)
        REFERENCES carts(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_cart_items_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT,

    CONSTRAINT uq_cart_product
        UNIQUE (cart_id, product_id),

    INDEX idx_cart_items_product (product_id)
) ENGINE=InnoDB;
```

## 6. Orders

```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    subtotal DECIMAL(12,3) NOT NULL,
    total DECIMAL(12,3) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KWD',
    shipping_address JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_orders_subtotal
        CHECK (subtotal >= 0),

    CONSTRAINT chk_orders_total
        CHECK (total >= 0),

    CONSTRAINT chk_orders_status
        CHECK (status IN (
            'pending',
            'confirmed',
            'processing',
            'shipped',
            'completed',
            'cancelled'
        )),

    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    INDEX idx_orders_user_created (user_id, created_at),
    INDEX idx_orders_status_created (status, created_at)
) ENGINE=InnoDB;
```

## 7. Order Items

Product name and unit price are snapshots of the values at purchase time.

```sql
CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,3) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_order_items_quantity
        CHECK (quantity > 0),

    CONSTRAINT chk_order_items_unit_price
        CHECK (unit_price >= 0),

    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT,

    INDEX idx_order_items_product (product_id)
) ENGINE=InnoDB;
```

The line total is calculated as `quantity * unit_price`. The order subtotal and total are stored as purchase-time snapshots.

## 8. Payments

Each row represents a payment attempt or payment operation.

```sql
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'simulated',
    provider_reference VARCHAR(150) NULL,
    idempotency_key VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(12,3) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KWD',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_payments_amount
        CHECK (amount >= 0),

    CONSTRAINT chk_payments_status
        CHECK (status IN ('pending', 'succeeded', 'failed')),

    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE RESTRICT,

    CONSTRAINT uq_payment_provider_reference
        UNIQUE (provider, provider_reference),

    INDEX idx_payments_order_status (order_id, status)
) ENGINE=InnoDB;
```

## 9. Initial Schema Decisions

- Use foreign keys to maintain referential integrity.
- Use unique constraints for email, slugs, order numbers, cart/product combinations, and payment idempotency keys.
- Use DECIMAL columns for monetary amounts.
- Preserve product names and prices in order items.
- Restrict deletion of records referenced by historical orders.
- Use transactions and suitable row locking during checkout.
- Recalculate prices and totals on the server.
- Enforce authorization in the application, not only in the database.

## 10. Not Yet Included

- Guest-cart persistence and merging.
- Product images and image storage.
- Shipping-provider integrations.
- Tax, coupons, and discounts.
- Refunds and payment-provider webhooks.
- Inventory movement history.
- Multiple currencies.

These features require additional requirements and schema decisions before implementation.