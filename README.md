# Laravel Cart

[![Latest Version on Packagist](https://img.shields.io/packagist/v/offline-agency/laravel-cart.svg?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-cart)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/laravel-cart.svg?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-cart)
[![PHP](https://img.shields.io/badge/php-8.4%20%7C%208.5-777bb4?style=flat-square)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-12%20%7C%2013-ff2d20?style=flat-square)](composer.json)
[![CI](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml?query=branch%3Amain)
[![codecov](https://codecov.io/gh/offline-agency/laravel-cart/branch/main/graph/badge.svg)](https://app.codecov.io/gh/offline-agency/laravel-cart)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](LICENSE)

---

## Overview

`offline-agency/laravel-cart` is a Laravel shopping cart with first-class VAT and fiscal support. It was forked from `Crinsane/LaravelShoppingcart` and re-namespaced under `OfflineAgency\LaravelCart`. It provides session and database storage drivers, item-level and cart-level coupons, tax breakdowns, Artisan commands, API resources and a testing fake.

---

## Compatibility

| PHP / Laravel | 12.x | 13.x |
|:---:|:---:|:---:|
| 8.4 | Yes | Yes |
| 8.5 | Yes | Yes |

- Tested floors: `illuminate/*` `^12.69.2 || ^13.32`. CI runs every PHP/Laravel combination with both the highest and the lowest allowed dependencies, so these floors are actually tested.
- Laravel 13 is the preferred target. Laravel 12 is supported while it receives security fixes.
- PHP 8.2 and 8.3 are not supported by this package (package policy). PHP 8.6 pre-releases are not supported yet.
- For Laravel 10 or 11, use the `3.x` line of this package.

---

## Installation

```bash
composer require offline-agency/laravel-cart
```

The service provider and the `Cart` facade are auto-discovered. Publish what you need:

```bash
# Configuration (config/cart.php)
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=config

# Translations (lang/vendor/laravel-cart)
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=laravel-cart-lang

# Tables for the "database" storage driver (carts + cart_items)
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-database-migrations

# Table for Cart::store() / Cart::restore() (see "Saving and restoring carts")
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=migrations

php artisan migrate
```

You only need the migrations for the features you use.

---

## Quick Start

```php
use OfflineAgency\LaravelCart\Facades\Cart;

// add(id, name, subtitle, qty, price (net), totalPrice (gross), vat)
Cart::add(1, 'Product A', null, 2, 9.99, 12.19, 2.20);
Cart::add(2, 'Product B', null, 1, 19.99, 24.39, 4.40);

Cart::total();    // 48.77
Cart::vat();      // 8.8
Cart::subtotal(); // 39.97
Cart::count();    // 3

Cart::destroy();
```

All amounts are per unit. `price` is the net unit price, `totalPrice` the gross unit price and `vat` the VAT amount per unit.

---

## Configuration Reference

After publishing, the file is `config/cart.php`.

| Key | Type | Default | Description |
|---|---|---|---|
| `storage.driver` | `string` | `"session"` (`CART_STORAGE_DRIVER`) | Active-cart storage. Supported: `"session"`, `"database"`. Anything else throws `InvalidDriverException`. |
| `database.connection` | `string\|null` | `null` | Connection for all cart tables (`null` = default connection). |
| `database.table` | `string` | `"cart"` | Table used by `store()` / `restore()` and most Artisan commands. |
| `database.carts_table` | `string` | `"carts"` | Carts table of the database driver. |
| `database.cart_items_table` | `string` | `"cart_items"` | Cart lines table of the database driver. |
| `users.foreign_key` | `string` | `"user_id"` | Column of `carts` that holds the authenticated owner. |
| `users.foreign_key_type` | `string` | `"unsignedBigInteger"` | Schema type of that column, used by the migration (`unsignedBigInteger`, `uuid`, `ulid`, ...). |
| `users.table` | `string` | `"users"` | Users table name. Currently not read by the package (no foreign key constraint is created). |
| `destroy_on_logout` | `bool` | `false` | Forget the session `cart` key (all instances) on logout. Session driver only. |
| `ttl` | `int\|null` | `null` | Minutes before a cart of the database driver expires (`null` = never). |
| `middleware.merge_on_login` | `bool` | `false` | Enables `CartMergeMiddleware`. |
| `middleware.auto_restore` | `bool` | `false` | Enables `CartRestoreMiddleware`. |
| `format.decimals` | `int` | `2` | Decimals used to round every computed amount and in `vatBreakdown()`. |
| `format.decimal_point` | `string` | `"."` | Decimal separator for formatted strings. |
| `format.thousand_separator` | `string` | `","` | Thousands separator for formatted strings. |
| `format.rounding_mode` | `int\|RoundingMode` | `PHP_ROUND_HALF_UP` | A `PHP_ROUND_HALF_*` constant or a `\RoundingMode` case. Any other value throws `InvalidConfigurationException`. |
| `limits.max_items` | `int\|null` | `null` | Maximum number of distinct rows. |
| `limits.max_qty_per_item` | `int\|null` | `null` | Maximum quantity of a single row. |
| `limits.max_total` | `float\|null` | `null` | Maximum gross total (before cart-level coupons). |
| `limits.min_total` | `float\|null` | `null` | Minimum total, checked only by `Cart::meetsMinimum()`. |
| `tax.default_rate` | `float` | `0.0` | Rate applied when an item is added without a VAT amount. |
| `tax.tax_behavior` | `string` | `"inclusive"` | `"inclusive"` (price includes tax) or `"exclusive"` (tax added on top). |
| `tax.fiscal_codes.enabled` | `bool` | `false` | Reserved. Currently not read by the package. |

One more key is read but not present in the published file:

| Key | Type | Default | Description |
|---|---|---|---|
| `use_legacy_events` | `bool` | `true` | Also dispatch the string events (`cart.added`, ...). See [Events](#events). |

---

## Usage

### Adding items

**Scalar arguments:**

```php
use OfflineAgency\LaravelCart\Facades\Cart;

// add(id, name, subtitle, qty, price, totalPrice, vat, vatFcCode, productFcCode, urlImg, createdAt, updatedAt, options)
$item = Cart::add(1, 'Product Name', 'Subtitle', 2, 10.00, 12.20, 2.20);
$item->rowId; // identifies the row; derived from the id and the options
```

Adding the same id with the same options again increases the quantity of the existing row.

**From a `Buyable` model** (the second argument is the quantity, default 1; the model is associated automatically):

```php
$product = Product::find(1);
Cart::add($product);
Cart::add($product, 3);
```

**From an array:**

```php
Cart::add(['id' => 1, 'name' => 'Widget', 'qty' => 3, 'price' => 5.00]);
```

Accepted keys: `id`, `name`, `qty`, `price`, `subtitle`, `totalPrice`, `vat`, `vatFcCode`, `productFcCode`, `urlImg`, `options`. An array of arrays (or of `Buyable` models) adds several items and returns an array of `CartItem`.

**Batch add** (reads `id`, `name`, `subtitle`, `qty`, `price`, `totalPrice`, `vat` from each entry):

```php
Cart::addBatch([
    ['id' => 1, 'name' => 'A', 'qty' => 1, 'price' => 10.00],
    ['id' => 2, 'name' => 'B', 'qty' => 2, 'price' => 20.00],
]); // returns the cart content
```

**Item options** (part of the row id, so the same product with different options is a different row):

```php
Cart::add(1, 'T-shirt', null, 1, 20.00, 24.40, 4.40, '', '', '', null, null, ['size' => 'L']);
Cart::get($rowId)->options->size; // "L"
```

### Updating items

```php
Cart::update($rowId, 5);                                    // set the quantity
Cart::update($rowId, ['name' => 'New Name', 'qty' => 3]);   // id, qty, name, price, options
Cart::update($rowId, $product);                             // from a Buyable (id, name, price, vat)
```

`update()` returns the updated `CartItem`, or `null` when the quantity drops to zero or below (the row is removed). Changing `id` or `options` with the array form changes the row id; if the new row id already exists, the quantities are merged.

### Removing items

```php
Cart::remove($rowId);
Cart::destroy(); // empties the current instance, including cart-level coupons, metadata and cart options
```

### Retrieving content

```php
Cart::content();      // Collection<string, CartItem>, keyed by row id
Cart::get($rowId);    // CartItem, or InvalidRowIDException
Cart::first();        // first CartItem (optionally matching a closure), or null
Cart::where('id', 1); // items whose attribute equals the value
Cart::count();        // sum of the quantities
Cart::uniqueCount();  // number of rows
Cart::isEmpty();
Cart::isNotEmpty();
Cart::sum('qty');     // sum of any item attribute
```

### Multiple instances

```php
Cart::instance('wishlist')->add($product);
Cart::currentInstance(); // "wishlist"
Cart::instance()->content(); // back to "default"
```

`instance()` switches the shared cart object, so later `Cart::` calls in the same request or job stay on that instance until you switch again.

### Model association

```php
$item = Cart::add(1, 'Shirt', null, 1, 29.99, 36.59, 6.60);
Cart::associate($item->rowId, \App\Models\Product::class); // a class name or a model instance

Cart::get($item->rowId)->associatedModel; // "App\Models\Product"
Cart::get($item->rowId)->model;           // what was passed to associate()
```

Adding a `Buyable` associates the model instance automatically. Associating a class name that does not exist throws `UnknownModelException`.

### Search

```php
Cart::search(fn ($item) => $item->id === 1);           // Collection
Cart::isAlreadyAdded(1, \App\Models\Product::class);   // bool (id + associated model)
Cart::searchById(1, \App\Models\Product::class);       // ?CartItem
```

### Serialization

```php
Cart::toArray();   // instance, items, count, uniqueCount, subtotal, vat, total, discount, coupons, meta, isEmpty
Cart::toJson();
Cart::snapshot();  // toArray() plus an ISO 8601 "timestamp"
```

### Metadata and cart options

```php
Cart::setMeta('notes', 'Leave at door');
Cart::getMeta('notes');          // "Leave at door" (dot notation and a default are supported)
Cart::meta();                    // ['notes' => 'Leave at door']
Cart::forgetMeta('notes');

Cart::setOptions(['gift' => true]);
Cart::getOptions();              // ['gift' => true]
Cart::getOptionsByKey('gift');   // true
```

Metadata and options are stored per instance by the active storage driver and are cleared by `destroy()`.

### Limits

```php
// config/cart.php
'limits' => [
    'max_items'        => 50,
    'max_qty_per_item' => 10,
    'max_total'        => 9999.99,
    'min_total'        => 5.00,
],
```

`add()` and `update()` (and so `addBatch()` and `sync()`) check `max_items`, `max_qty_per_item` and `max_total` against the cart as it would be after the change. If a limit is exceeded they throw `CartLimitExceededException`, and the cart is left unchanged with no events dispatched.

```php
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;

try {
    Cart::add($product, 20);
} catch (CartLimitExceededException $e) {
    $e->limitType; // translated label, e.g. "maximum quantity per item"
    $e->limit;     // 10
    $e->attempted; // 20
}
```

`min_total` is never enforced automatically. Check it before checkout:

```php
Cart::meetsMinimum(); // bool, compares the total before cart-level coupons
```

### Weight

`Cart::weight()` sums the `weight` item option multiplied by the quantity:

```php
Cart::add(1, 'Box', null, 2, 5.00, 6.10, 1.10, '', '', '', null, null, ['weight' => 1.5]);
Cart::weight(); // 3.0
```

`Buyable::getWeight()` is not read automatically. To use it, pass it as the `weight` option (for example from your model's `getOptions()`).

### Syncing with a list

```php
Cart::sync([
    ['id' => 1, 'name' => 'A', 'qty' => 2, 'price' => 10.00],
    ['id' => 3, 'name' => 'C', 'qty' => 1, 'price' => 5.00],
]);
```

Rows whose id is not in the list are removed, existing ids get the new quantity, and new ids are added.

---

## Tax and Rounding

### How VAT is computed

When you pass a `vat` amount, it is used as is and the rate is derived from it (`vatRate = 100 * vat / price`).

When `vat` is omitted (`null`) and `tax.default_rate` is greater than zero, the default rate is applied:

- **inclusive**: the gross amount (`totalPrice`, or `price` when `totalPrice` is omitted) is split into net and VAT.
- **exclusive**: `price` is the net amount and the VAT is added on top.

```php
// config/cart.php: 'tax' => ['default_rate' => 22.0, 'tax_behavior' => 'inclusive']
$item = Cart::add(5, 'Inclusive', null, 1, 122.00);
// $item->price 100.0, $item->vat 22.0, $item->totalPrice 122.0, $item->vatRate 22.0

// config/cart.php: 'tax' => ['default_rate' => 22.0, 'tax_behavior' => 'exclusive']
$item = Cart::add(5, 'Exclusive', null, 1, 100.00);
// $item->price 100.0, $item->vat 22.0, $item->totalPrice 122.0
```

Per-item rates are expressed by passing the VAT amount:

```php
Cart::add(1, 'Standard', null, 1, 81.97, 100.00, 18.03);  // ~22%
Cart::add(2, 'Reduced',  null, 1, 90.91, 100.00, 9.09);   // ~10%
```

### Totals

```php
Cart::subtotal();       // net total of the items
Cart::vat();            // VAT total of the items
Cart::total();          // gross total minus cart-level coupons, never below 0
Cart::discount();       // amount removed by cart-level coupons
Cart::totalVatLabel();  // "VAT Included" when vat() > 0, otherwise "Tax Exempt" (translated)
Cart::taxName();        // "VAT" (translated)
```

Every amount is rounded with `format.decimals` and `format.rounding_mode`. The totals return floats. Use `numberFormat()` for display strings:

```php
Cart::numberFormat(Cart::total(), null, null, null); // "1,234.50" with the configured format
```

### VAT breakdown (fiscal receipts)

```php
Cart::vatBreakdown();
// Collection of one entry per rate:
// ['rate' => 22.0, 'net' => '100.00', 'vat' => '22.00', 'tax' => '22.00', 'gross' => '122.00', 'tax_name' => 'VAT']
```

Amounts are formatted strings. The breakdown includes item-level coupons but not cart-level coupons (see below).

### Configuration examples

```php
// EU, VAT included at 22%
'tax' => ['default_rate' => 22.0, 'tax_behavior' => 'inclusive'],

// US, sales tax added on top at 8.875%
'tax' => ['default_rate' => 8.875, 'tax_behavior' => 'exclusive'],

// Tax-free
'tax' => ['default_rate' => 0.0, 'tax_behavior' => 'inclusive'],
```

To rename the tax (e.g. "GST"), publish the translations and change `tax_name` in `lang/vendor/laravel-cart/{locale}/cart.php`.

### Buyable and FiscalBuyable

`Buyable` is a getter-only contract (`getId`, `getName`, `getSubtitle`, `getQty`, `getPrice`, `getTotalPrice`, `getVat`, `getUrlImg`, `getOptions`, `getWeight`). The `CanBeBought` trait implements all of them from common attributes.

For fiscal codes (VAT FC code and product FC code), implement `FiscalBuyable`. The `HasFiscalCodes` trait reads `vat_fc_code` and `product_fc_code`:

```php
use Illuminate\Database\Eloquent\Model;
use OfflineAgency\LaravelCart\CanBeBought;
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;
use OfflineAgency\LaravelCart\Traits\HasFiscalCodes;

class Product extends Model implements FiscalBuyable
{
    use CanBeBought;
    use HasFiscalCodes;
}

$item = Cart::add(Product::find(1));
$item->vatFcCode;
$item->productFcCode;
```

---

## Coupons and Discounts

There are two independent kinds of coupon.

| | Item-level | Cart-level |
|---|---|---|
| Added with | `addItemCoupon($rowId, $code, $type, $value)` | `addCoupon()` or `addGlobalCoupon()` |
| Changes | the row's `price`, `vat`, `totalPrice` | only `total()` and `discount()` |
| Reflected in `subtotal()`, `vat()`, `vatBreakdown()` | Yes | No |
| Listed by | `coupons()`, `getCoupons()`, `hasCoupon()` | `listCoupons()`, `getGlobalCoupons()`, `hasCartCoupon()` |

### Item-level coupons

```php
$item = Cart::add(1, 'Shirt', null, 3, 81.97, 100.00, 18.03);

Cart::addItemCoupon($item->rowId, 'ITEM20', 'percentage', 20.0);
Cart::total(); // 280.0: the discount applies to one unit of the row, not to every unit

Cart::detachCoupon($item->rowId, 'ITEM20'); // or Cart::removeCoupon('ITEM20')
Cart::removeAllCoupons();                    // detaches every item-level coupon
```

- `fixed` subtracts the value from the row's gross unit price. `percentage` subtracts that percentage of the original gross unit price. Net price and VAT are then recomputed from the row's VAT rate.
- The discount is applied once per row: the cart counts the first unit at the discounted price and the remaining units at the original price.
- Passing `null` as the row id falls back to the deprecated `applyGlobalCoupon()`, which adds a negative `discountCartItem` row. Use cart-level coupons instead.

### Cart-level coupons

```php
use OfflineAgency\LaravelCart\CartCoupon;

$coupon = new CartCoupon(
    hash: 'abc123',
    code: 'SAVE10',
    type: 'percentage', // or 'fixed'
    value: 10.0,
    isGlobal: true,
    expiresAt: now()->addWeek(),  // optional
    minCartAmount: 50.0,          // optional
);

Cart::addCoupon($coupon);              // CartCoupon, Couponable or a code string
Cart::hasCartCoupon('SAVE10');         // by hash or code
Cart::listCoupons();                   // Collection<string, CartCoupon> keyed by hash
Cart::discount();                      // total removed by cart-level coupons
Cart::removeCartCoupon('SAVE10');      // by hash or code
```

- `addCoupon()` validates the coupon. It throws `CouponAlreadyAppliedException` if the same hash is already applied, and `InvalidCouponException` if the coupon is expired or the total before cart-level coupons is below `minCartAmount`. It dispatches `CouponApplied`. A plain string creates a `fixed` coupon with value 0 (a code marker).
- `removeCartCoupon()` throws `CouponNotFoundException` for unknown coupons and dispatches `CouponRemoved`.
- `addGlobalCoupon($hash, $code, $type, $value)` and `removeGlobalCoupon($hash)` are the older API. They do no validation and dispatch only the string events.
- **Calculation:** percentage coupons are applied first, each on the remaining amount. Then fixed coupons are subtracted, each capped at what remains, so `total()` never goes below zero. For example, 300.00 with 10% and 5.00 fixed gives `total()` 265.00 and `discount()` 35.00.
- `usageLimit` is stored on the coupon for your own use. The package does **not** count redemptions or enforce it.

`syncCoupons()` re-validates cart-level coupons (expiry and minimum amount) against the current cart, removes the invalid ones without events, and returns their codes:

```php
$removedCodes = Cart::syncCoupons();
```

### Eloquent coupons: Couponable and HasDiscount

```php
use Illuminate\Database\Eloquent\Model;
use OfflineAgency\LaravelCart\Contracts\Couponable;
use OfflineAgency\LaravelCart\Traits\HasDiscount;

class Promo extends Model implements Couponable
{
    use HasDiscount;
}

Cart::addCoupon(Promo::find(1));
```

`HasDiscount` uses the primary key as the hash and reads the columns `coupon_code`, `coupon_type` (`fixed`|`percentage`), `coupon_value`, `coupon_expires_at`, `coupon_usage_limit` and `coupon_min_cart_amount`.

---

## Storage

There are two separate persistence mechanisms:

1. **The storage driver** (`storage.driver`) holds the live cart, read and written by every cart call.
2. **`store()` / `restore()`** save and reload an explicit snapshot in the `cart` table under an identifier you choose. This works the same with either driver.

### Session driver (default)

No setup. Each instance is kept in the session under `cart.{instance}`, together with its cart-level coupons, cart options and metadata. The cart lives as long as the session.

### Database driver

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-database-migrations
php artisan migrate
```

```dotenv
CART_STORAGE_DRIVER=database
```

Carts are stored in `carts` (one row per owner and instance: coupons, options, meta, `expires_at`) and `cart_items` (one row per cart line).

**Owner.** The owner of a cart is resolved on every call:

- **Authenticated user:** `auth()->id()` is stored in the `cart.users.foreign_key` column (default `user_id`).
- **Guest:** the current session id is stored in the nullable `session_id` column. A session id is never written to the user column.

The user column is nullable and indexed. Its schema type comes from `cart.users.foreign_key_type` (`unsignedBigInteger`, `uuid`, `ulid`, ...). Set both keys **before** running the migration, because they are read when the table is created. Unique indexes on (user column, `instance`) and (`session_id`, `instance`) guarantee one cart per owner and instance.

**Guest to login.** Laravel regenerates the session id when a user logs in, and the owner switches from the session id to the user id. The cart a guest built with the database driver is therefore **not** visible after login, and the package does not move it automatically. `CartMergeMiddleware` does not move it either: it merges the user's *stored snapshot* (see below). To keep a guest cart, carry it over yourself. For example, store it under an identifier you control before authenticating, then restore it with `mergeOnRestore: true` afterwards.

**TTL.** With `cart.ttl` set (minutes), `expires_at` is set when the cart row is **created**. Later writes do not extend it. An expired cart is treated as missing and deleted the next time it is read. To bulk-delete expired carts, run `php artisan cart:prune --expired-only`. The TTL applies only to this driver.

**Writes and concurrency.**

- Every write runs in one database transaction that locks the cart row (`lockForUpdate`).
- Concurrent creation of the same cart is race-tolerant: the unique index plus `insertOrIgnore` means the loser re-reads the winner's row.
- Writing the items replaces all lines of that cart. A failed write rolls back and leaves the previous cart intact.
- There is no optimistic versioning: if two requests change the same cart at the same time, **the last committed write wins**.
- Removing coupons, metadata or cart options clears only that column and never deletes the items.

**Round trip.** Row ids, quantities, original and discounted prices, VAT rate, applied item coupons, discount value, options, associated model class and item timestamps survive a save and reload.

**Read cache.** Within one request or job, reads are cached per owner and instance. The cache re-checks the TTL before it is used.

### Lifetime of the cart object

`CartStorage` and the `cart` binding (the facade root) are registered as **scoped** bindings. You get one instance per HTTP request, per queued job and per Octane request. The current instance name, the loaded cart-level coupons and the read cache never leak between users on a long-running worker. Inside a single request, calls through the facade share the same object.

### Custom drivers

Implement `OfflineAgency\LaravelCart\Contracts\CartStorage`:

```php
use OfflineAgency\LaravelCart\Contracts\CartStorage;

class RedisCartStorage implements CartStorage
{
    public function get(string $key, mixed $default = null): mixed { /* ... */ }
    public function put(string $key, mixed $value): void { /* ... */ }
    public function has(string $key): bool { /* ... */ }
    public function remove(string $key): void { /* ... */ }
}
```

The keys are `cart.{instance}` (a `Collection` of `CartItem`) and the same key with the suffixes `_global_coupons`, `_cart_info` and `_meta`. Bind the class in a service provider. Use `scoped` so it keeps the per-request lifetime:

```php
$this->app->scoped(CartStorage::class, RedisCartStorage::class);
```

### Saving and restoring carts (`store()` / `restore()`)

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=migrations
```

The tag publishes three migrations, timestamped so they run in order: the `cart.database.table` table (`identifier`, `instance`, `content`, timestamps) and its nullable JSON `coupons` and `meta` columns, which `store()` writes.

Upgrading from 3.x, where only the table migration was published? Publish just the two column migrations (they skip columns that already exist):

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-legacy-column-migrations
```

```php
Cart::store($identifier);                     // overwrites an existing snapshot by default
Cart::store($identifier, overwrite: false);   // throws CartAlreadyStoredException if one exists

Cart::restore($identifier);                                   // replace the instance content
Cart::restore($identifier, mergeOnRestore: true);             // keep current rows, add missing ones
Cart::restore($identifier, keepStored: true);                 // do not delete the snapshot
```

- `store()` saves the current instance's items, cart-level coupons and metadata. It dispatches `CartStored`.
- `restore()` loads the snapshot into the instance it was stored from, then switches back to the instance that was current. It restores items, cart-level coupons and metadata. Unless `keepStored` is set, it deletes that snapshot. It does nothing if no snapshot exists, and throws `CartStorageException` if the stored content cannot be decoded. It dispatches `CartRestored`.
- Snapshots are keyed by identifier **and** instance, so one identifier can hold a snapshot per instance (`Cart::instance('wishlist')->store($userId)`). `restore()` prefers the snapshot of the current instance and falls back to any instance stored under the identifier; it deletes only the snapshot it restored.
- **Encoding.** Content is written as base64 behind an `lcart:b64:` prefix, so it fits text columns on PostgreSQL. Rows written by earlier versions (raw `serialize()` output) are still read.
- **Restricted unserialize.** Decoding only instantiates the classes a cart contains: cart value objects, collections, `stdClass`, date/Carbon objects and associated models (`Buyable` implementations or Eloquent models). Any other class comes back as `__PHP_Incomplete_Class` and its magic methods do not run.

---

## Middleware

Both middleware work on `store()` / `restore()` snapshots whose identifier is the user's auth identifier (`$user->getAuthIdentifier()`). Both swallow restore errors.

### CartMergeMiddleware

```php
'middleware' => ['merge_on_login' => true],
```

After the response has been produced, for an authenticated user, it restores the snapshot stored under the user's id with `mergeOnRestore: true` and `keepStored: true`. Rows already in the current cart win. This runs at most once per session id, tracked with a `cart_guest_merged_{sessionId}` session flag. It does not move a guest cart of the database driver (see [Guest to login](#database-driver)).

### CartRestoreMiddleware

```php
'middleware' => ['auto_restore' => true],
```

Before the request is handled, for an authenticated user whose current cart is empty, it restores the snapshot stored under the user's id (merge, keep stored).

### Registration

```php
// bootstrap/app.php
use OfflineAgency\LaravelCart\Http\Middleware\CartMergeMiddleware;
use OfflineAgency\LaravelCart\Http\Middleware\CartRestoreMiddleware;

->withMiddleware(function ($middleware) {
    $middleware->web(append: [
        CartMergeMiddleware::class,
        CartRestoreMiddleware::class,
    ]);
})
```

---

## Events

All typed events are `final readonly` classes in `OfflineAgency\LaravelCart\Events`:

| Event | Properties | Dispatched by |
|---|---|---|
| `CartItemAdded` | `CartItem $cartItem` | `add()` (also via `addBatch()`, `sync()`) |
| `CartItemUpdated` | `CartItem $cartItem` | `update()` |
| `CartItemRemoved` | `CartItem $cartItem` | `remove()`, `update()` to zero |
| `CartStored` | `mixed $identifier`, `string $instance` | `store()` |
| `CartRestored` | `mixed $identifier`, `string $instance` | `restore()` |
| `CouponApplied` | `CartCoupon $coupon`, `string $cartInstance` | `addCoupon()` |
| `CouponRemoved` | `CartCoupon $coupon`, `string $cartInstance` | `removeCartCoupon()` |

```php
use Illuminate\Support\Facades\Event;
use OfflineAgency\LaravelCart\Events\CartItemAdded;

Event::listen(function (CartItemAdded $event) {
    logger()->info('Added', ['rowId' => $event->cartItem->rowId]);
});
```

Unless `cart.use_legacy_events` is `false`, the string events from the 3.x API are dispatched as well (see the `CartEventType` enum): `cart.added`, `cart.updated`, `cart.removed`, `cart.stored`, `cart.restored`, `cart.coupon_removed`, `cart.coupons_cleared`, `cart.global_coupon_added`, `cart.global_coupon_removed`.

---

## Artisan Commands

Unless noted, the commands work on the `store()` / `restore()` table (`cart.database.table`).

| Command | Options | Description |
|---|---|---|
| `cart:clear` | `--force`, `--instance=` | Delete stored snapshots (all, or one instance). |
| `cart:stats` | `--instance=`, `--json` | Statistics about stored snapshots. |
| `cart:list` | `--instance=`, `--limit=50`, `--sort=newest\|oldest\|identifier`, `--json` | List stored snapshots. |
| `cart:inspect {identifier}` | `--instance=default`, `--json`, `--raw` | Show one stored snapshot in detail. |
| `cart:prune` | `--hours=24`, `--instance=`, `--expired-only`, `--dry-run`, `--force` | Delete snapshots not updated within `--hours`. With `--expired-only`, it deletes expired carts of the **database driver** (`carts` / `cart_items`) instead. |
| `cart:export` | `--path=storage/app/cart-export.json`, `--instance=`, `--since=Y-m-d`, `--pretty` | Export stored snapshots to JSON. |
| `cart:migrate-driver` | `--from=session`, `--to=database`, `--dry-run`, `--force`, `--chunk=100` | Copy carts found in `database` or `file` sessions into the snapshot table (identifier = session `user_id`, or the session id). Existing snapshots are skipped and cart-level coupons are not copied. `database` to `session` is not supported. |

---

## API Resources

```php
use OfflineAgency\LaravelCart\Resources\CartItemResource;
use OfflineAgency\LaravelCart\Resources\CartResource;

return new CartResource(app('cart'));                 // Cart::toArray()
return CartItemResource::collection(Cart::content()); // CartItem::toArray() for each row
```

---

## Localization

Translations ship for English (`en`) and Italian (`it`) under the `laravel-cart` namespace.

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=laravel-cart-lang
```

Edit `lang/vendor/laravel-cart/{locale}/cart.php`, or copy the `en` folder to add a locale.

---

## Testing

`Cart::fake()` swaps the facade for a `CartFake` that records the typed events dispatched through it:

```php
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Events\CartItemRemoved;
use OfflineAgency\LaravelCart\Facades\Cart;

it('dispatches an event when adding an item', function () {
    $cart = Cart::fake();

    Cart::add(1, 'Test Product', null, 1, 9.99, 12.19, 2.20);

    $cart->assertDispatched(CartItemAdded::class);
    $cart->assertDispatched(CartItemAdded::class, fn (CartItemAdded $e) => $e->cartItem->id === 1);
    $cart->assertDispatchedTimes(CartItemAdded::class, 1);
    $cart->assertNotDispatched(CartItemRemoved::class);
});
```

| Method | Description |
|---|---|
| `assertDispatched(string $event, ?callable $callback = null)` | The event was dispatched (at least one matching the callback). |
| `assertNotDispatched(string $event)` | The event was never dispatched. |
| `assertDispatchedTimes(string $event, int $times = 1)` | The event was dispatched exactly `$times` times. |

The fake always uses session storage, whatever driver is configured. Events still reach the real dispatcher, so listeners run (combine with `Event::fake()` if you need that). Only the facade is swapped: `app('cart')` and injected `Cart` instances are not.

---

## Development

```bash
composer install
composer check          # validate:strict + lint:check + analyse + test
composer test           # Pest
composer test:coverage  # Pest with coverage (minimum 100%)
composer analyse        # PHPStan (Larastan) at max level
composer lint           # fix code style (Pint)
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for running the suite against PostgreSQL, lowest-dependency checks and pull request conventions, and [LARASTAN.md](LARASTAN.md) for static analysis.

---

## Releases

Every pull request merged into `main` gets its own SemVer tag (unprefixed, e.g. `4.0.0`) and GitHub Release once CI has passed on its merge commit. The bump comes from a `release:major|minor|patch` label or the Conventional Commit title. See [.github/RELEASING.md](.github/RELEASING.md). Changes are listed in [CHANGELOG.md](CHANGELOG.md).

---

## Upgrading

See [UPGRADE.md](UPGRADE.md) for upgrading from 3.x to 4.x.

---

## License

Open-source software licensed under the [MIT License](LICENSE).
