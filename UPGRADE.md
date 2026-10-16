# Upgrading Laravel Cart

## From 4.0.x to the next release (unreleased)

Latest verified stable tag is 4.0.3; the following changes are pending PR #50 and
this follow-up, not a guessed numbered release.

- Install ext-bcmath before upgrading.
- Price updates now change coherent base net/VAT/gross and reapply retained row coupons;
  use update() to reprice. Invalid/non-finite coupons and contradictory amounts are rejected.
- Logical built-in mutations stage/validate/persist atomically. Database package
  mutations lock before reading; successful events wait for outer transaction commit.
  See [storage guarantees](docs/v4/storage.md) for custom-driver/raw-write/session limits.
- Live guest transfer is opt-in and distinct from saved snapshots. Register the
  [middleware around login after StartSession](docs/v4/authentication.md).
- payableTaxBreakdown() is additive: legacy subtotal/vat/vatBreakdown remain before
  cart coupons. Read [allocation domains and residual rules](docs/v4/post-discount-tax.md).
- Use the [capability map](docs/v4/capabilities.md) and [tested recipe service](docs/v4/recipes/index.md).

## From v3 to released 4.0.0

This guide covers the breaking changes and new features of `offline-agency/laravel-cart` 4.0.0 compared with the 3.x line.

---

## Requirements

| Requirement | v3 (3.x) | v4 (4.x) |
|-------------|----------|----------|
| PHP         | >= 8.4 (`^8.4`) | 8.4 or 8.5 (`~8.4.0 \|\| ~8.5.0`) |
| Laravel     | 10, 11, 12, 13 | 12 (12.69.2+), 13 (13.32+) |

- The `illuminate/*` floors (`^12.69.2 || ^13.32`) are the lowest versions tested in CI.
- Laravel 10 and 11 are no longer supported. Stay on `^3.0` if you cannot upgrade Laravel yet.
- PHP 8.2 and 8.3 are not supported (package policy).

---

## Breaking Changes

### 1. Buyable Interface (Getter-Only, New `getWeight()`)

The `Buyable` interface no longer declares setter methods or fiscal-code methods. It is now a **getter-only** contract, with a new `getWeight(): float` method.

If your model implemented `Buyable` with custom setters, you can keep them, but they are no longer part of the contract. The `CanBeBought` trait still provides setters for convenience.

**Before (v3):**

```php
use OfflineAgency\LaravelCart\Contracts\Buyable;

class Product extends Model implements Buyable
{
    public function getId() { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getPrice(): float { return $this->price; }
    // Setters were part of the interface
    public function setName(string $name): void { $this->name = $name; }
    public function setPrice(float $price): void { $this->price = $price; }
    // ... (other getters/setters, getVatFcCode(), getProductFcCode())
    // No getWeight() required
}
```

**After (v4):**

```php
use OfflineAgency\LaravelCart\Contracts\Buyable;

class Product extends Model implements Buyable
{
    public function getId() { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSubtitle(): string { return $this->subtitle ?? ''; }
    public function getQty(): int { return 1; }
    public function getPrice(): float { return (float) $this->price; }
    public function getTotalPrice(): float { return $this->getPrice() + $this->getVat(); }
    public function getVat(): float { return (float) ($this->vat ?? 0.0); }
    public function getUrlImg(): string { return $this->image ?? ''; }
    public function getOptions(): array { return []; }
    public function getWeight(): float { return (float) ($this->weight ?? 0.0); } // NEW
    // Setters are no longer part of the interface
}
```

Or use the `CanBeBought` trait. It implements every getter, including `getWeight()`, and still provides setters:

```php
use OfflineAgency\LaravelCart\CanBeBought;
use OfflineAgency\LaravelCart\Contracts\Buyable;

class Product extends Model implements Buyable
{
    use CanBeBought;
}
```

> `Cart::weight()` sums the `weight` **item option** × quantity. It does not call `getWeight()`, so pass the weight as an option if you need it in the cart.

### 2. Fiscal Code Methods Moved to `FiscalBuyable`

`getVatFcCode()` and `getProductFcCode()` have moved from `Buyable` to a new `FiscalBuyable` interface, which extends `Buyable`. If your model uses fiscal codes, implement `FiscalBuyable`. The `HasFiscalCodes` trait reads them from the `vat_fc_code` and `product_fc_code` attributes. Items added from a plain `Buyable` get empty fiscal codes.

**Before (v3):**

```php
use OfflineAgency\LaravelCart\Contracts\Buyable;

class Product extends Model implements Buyable
{
    public function getVatFcCode(): string { return $this->vat_fc_code; }
    public function getProductFcCode(): string { return $this->product_fc_code; }
    // ... other Buyable methods
}
```

**After (v4):**

```php
use OfflineAgency\LaravelCart\CanBeBought;
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;
use OfflineAgency\LaravelCart\Traits\HasFiscalCodes;

class Product extends Model implements FiscalBuyable
{
    use CanBeBought;     // or implement the Buyable getters yourself
    use HasFiscalCodes;
}
```

### 3. Exception Hierarchy

All package exceptions now extend the abstract `OfflineAgency\LaravelCart\Exceptions\CartException`, which extends `RuntimeException`. In v3 they extended `RuntimeException` directly. Existing `catch (RuntimeException $e)` blocks keep working. `CartException` lets you catch only cart errors.

New exception classes:

- `CartStorageException`: thrown by `restore()` when the stored content cannot be decoded (see section 13).
- `InvalidDriverException`: thrown when `cart.storage.driver` is not `session` or `database`.
- `CartLimitExceededException`: thrown when a cart limit is exceeded (see section 7).
- `CartExpiredException`: defined for expired carts but **not currently thrown** by the package. An expired cart of the database driver is treated as missing (empty).

**Before (v3):**

```php
use RuntimeException;

try {
    Cart::store($identifier);
} catch (RuntimeException $e) {
    // ...
}
```

**After (v4):**

```php
use OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException;
use OfflineAgency\LaravelCart\Exceptions\CartException;

try {
    Cart::store($identifier, overwrite: false);
} catch (CartAlreadyStoredException $e) {
    // Specific handling
} catch (CartException $e) {
    // Any other cart exception
}
```

### 4. Storage Drivers (CartStorage Interface)

`Cart` no longer depends on `Illuminate\Session\SessionManager` directly. It depends on the `OfflineAgency\LaravelCart\Contracts\CartStorage` interface. The default driver is still `session`.

**Config change.** Add to `config/cart.php`:

```php
'storage' => [
    'driver' => env('CART_STORAGE_DRIVER', 'session'),
],
```

If you construct `Cart` yourself, pass a `CartStorage` instead of the `SessionManager`:

**Before (v3):**

```php
$cart = new Cart($sessionManager, $events);
```

**After (v4):**

```php
use OfflineAgency\LaravelCart\Contracts\CartStorage;

$cart = new Cart($cartStorage, $events);

// Or let the container resolve it (recommended)
$cart = app('cart');
```

To use the database driver, set `CART_STORAGE_DRIVER=database` in your `.env`, then publish and run the migrations:

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-database-migrations
php artisan migrate
```

See [Storage](docs/v4/storage.md) for current owners, TTL and concurrency guarantees.

### 5. Translations

All user-facing strings now go through Laravel's translator with `laravel-cart::cart.*` keys. The hardcoded Italian labels `"Iva Inclusa"` and `"Esente Iva"` are gone.

English and Italian translations are included. Publish them to customize:

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=laravel-cart-lang
```

**Before (v3):**

```php
$item->vatLabel; // "Iva Inclusa" or "Esente Iva" (always Italian)
```

**After (v4):**

```php
$item->vatLabel; // __('laravel-cart::cart.tax_included') or __('laravel-cart::cart.tax_exempt')
// English: "VAT Included" / "Tax Exempt"
// Italian: "IVA Inclusa" / "Esente IVA"
```

`Cart::totalVatLabel()` follows the same keys.

### 6. Tax Configuration

A new `cart.tax` config section controls default tax behavior.

**Add to `config/cart.php`:**

```php
'tax' => [
    'default_rate'  => 0.0,          // Applied when an item is added without a VAT amount
    'tax_behavior'  => 'inclusive',  // 'inclusive' (EU) or 'exclusive' (US)
    'fiscal_codes'  => [
        'enabled' => false,          // Reserved, currently not read
    ],
],
```

When `default_rate` is greater than zero and no VAT amount is passed (`vat` is `null`), the rate is applied according to `tax_behavior`:

- **inclusive**: the gross amount (`totalPrice`, or `price` when `totalPrice` is omitted) already includes tax. The VAT is extracted from it.
- **exclusive**: `price` is net and the tax is added on top.

With the default `0.0`, behavior is unchanged.

### 7. Cart Limits

A new `cart.limits` config section adds configurable constraints.

**Add to `config/cart.php`:**

```php
'limits' => [
    'max_items'        => null, // Max distinct rows in the cart
    'max_qty_per_item' => null, // Max quantity of a single row
    'max_total'        => null, // Max gross total before cart-level coupons
    'min_total'        => null, // Min total, checked only by Cart::meetsMinimum()
],
```

When a limit would be exceeded, `Cart::add()` and `Cart::update()` throw `CartLimitExceededException`. The limits are checked against a candidate copy of the cart, so a rejected operation leaves the cart **unchanged** and dispatches **no events**.

```php
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;

try {
    Cart::add($product);
} catch (CartLimitExceededException $e) {
    $e->limitType;  // translated label, e.g. "maximum items" (laravel-cart::cart.limit_max_items)
    $e->limit;      // e.g. 50
    $e->attempted;  // e.g. 51
}
```

### 8. `vatBreakdown()` Returns Additional Keys

Each entry of `Cart::vatBreakdown()` now also has a `'tax'` key and a `'tax_name'` key.

**Before (v3):**

```php
Cart::vatBreakdown();
// [['rate' => 22.0, 'net' => '100.00', 'vat' => '22.00', 'gross' => '122.00']]
```

**After (v4):**

```php
Cart::vatBreakdown();
// [
//     [
//         'rate'     => 22.0,
//         'net'      => '100.00',
//         'vat'      => '22.00',
//         'tax'      => '22.00',      // NEW (same value as 'vat')
//         'gross'    => '122.00',
//         'tax_name' => 'VAT',        // NEW (from translation)
//     ]
// ]
```

### 9. `store()` / `restore()` Behavior Changes

`Cart::store()` now **overwrites by default**. In v3, calling `store()` when a cart was already stored under that identifier threw `CartAlreadyStoredException`.

**Before (v3):**

```php
Cart::store('user-123'); // Throws CartAlreadyStoredException if already stored
```

**After (v4):**

```php
Cart::store('user-123');                   // Overwrites by default
Cart::store('user-123', overwrite: false); // v3 behavior: throws if already stored
```

`Cart::restore()` keeps the v3 `mergeOnRestore` flag and adds `keepStored`, which skips deleting the stored row:

```php
Cart::restore('user-123', mergeOnRestore: true, keepStored: true);
```

Snapshots are keyed by identifier and instance: storing a second instance under the same identifier adds a row instead of being silently dropped (a 3.x and pre-release bug), and `restore()` prefers the current instance's snapshot and deletes only the snapshot it restored.

### 10. `Cart::fake()` Returns `CartFake`

In v3, `Cart::fake()` swapped the facade for a plain `Cart` and offered no assertions. In v4 it returns a `CartFake` (a `Cart` subclass) that records the typed events and provides assertions, following the `Mail::fake()` / `Queue::fake()` pattern.

**Before (v3):**

```php
$cart = Cart::fake(); // a plain Cart, no assertions
Cart::add('1', 'Product', '', 1, 9.99);
// assert on the cart state or with Event::fake()
```

**After (v4):**

```php
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Events\CartItemRemoved;
use OfflineAgency\LaravelCart\Facades\Cart;

$cart = Cart::fake();

Cart::add('1', 'Product', '', 1, 9.99);

$cart->assertDispatched(CartItemAdded::class);
$cart->assertNotDispatched(CartItemRemoved::class);
$cart->assertDispatchedTimes(CartItemAdded::class, 1);
```

The fake always uses session storage, and events still reach the real dispatcher.

### 11. Deprecations

- `CartItem::$taxRate` is deprecated in favor of `$vatRate` (it holds the same value), and `CartItem::$tax` in favor of `$vat`. The deprecated properties will be removed in a future major version.
- The `'vat'` key of `vatBreakdown()` entries is deprecated in favor of `'tax'`.
- `Cart::applyCoupon()` is deprecated: use `addItemCoupon()` for item-level coupons or `addCoupon()` for cart-level coupons.
- `Cart::applyGlobalCoupon()` (also reached by `addItemCoupon(null, ...)`) is deprecated: use `addGlobalCoupon()` or `addCoupon()`.
- `CanBeBought::getBuyableIdentifier()`, `getBuyableDescription()` and `getBuyablePrice()` are deprecated: use `getId()`, `getName()` / `getSubtitle()` and `getPrice()`.

**Before (v3):**

```php
$rate = Cart::get($rowId)->taxRate;
```

**After (v4):**

```php
$rate = Cart::get($rowId)->vatRate;
```

### 12. Scoped Container Bindings

In v3, `cart` was registered as a **singleton**. In v4, both `cart` (the facade root) and `CartStorage` are **scoped** bindings. You get one instance per HTTP request, per queued job and per Octane request, so the current instance name, the loaded cart-level coupons and the database read cache no longer leak between users on long-running workers.

What to check:

- Do not keep a `Cart` instance in a static property or a singleton service across requests or jobs. Resolve it (or use the facade) where you need it.
- If you bind your own `CartStorage`, use `$this->app->scoped(...)` to keep the same lifetime.
- `Cart::instance('...')` switches the shared object for the rest of the current request or job only.

### 13. Stored Cart Content: Encoding, Restricted Unserialize and `restore()` Errors

These changes affect the `store()` / `restore()` table (`cart.database.table`, default `cart`) and the Artisan commands that read it.

- **Encoding.** `store()` now writes the content as base64 behind an `lcart:b64:` prefix (`OfflineAgency\LaravelCart\Support\CartContentCodec`), because raw `serialize()` output contains NUL bytes that PostgreSQL text columns reject. Rows written by v3 (raw `serialize()` output) are still read, and no data migration is needed. Rows written by v4 **cannot be read by v3**, so plan rollbacks accordingly.
- **Restricted unserialize.** Decoding only instantiates the classes a cart legitimately contains: `Collection` (and subclasses), `CartItem`, `CartItemOptions`, `stdClass`, `DateTimeInterface` implementations (Carbon), and associated models that implement `Buyable` or extend an Eloquent `Model`. Any other object stored in a cart item, for example a plain DTO passed to `associate()`, is restored as `__PHP_Incomplete_Class` and its magic methods do not run.
- **`restore()` exception.** If the stored content cannot be decoded (corrupt payload, or not a collection of cart items), `restore()` throws `CartStorageException` instead of failing in `unserialize()` or restoring garbage. Restoring an identifier with no stored row is still a silent no-op. `CartMergeMiddleware` and `CartRestoreMiddleware` swallow this exception.
- **Missing columns.** `store()` writes the `coupons` column, and the `meta` column when it exists. 3.x published only the table migration. Publish the two column migrations with `php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-legacy-column-migrations` and migrate; they skip columns that already exist. Fresh installs get all three from `--tag=migrations`.

### 14. Database Driver Schema and Behavior (released in 4.0.0)

The `carts` / `cart_items` migrations (`0002_..._create_carts_table.php`, `0003_..._create_cart_items_table.php`) were first released in 4.0.0. They were not part of earlier tagged releases. If you only use tagged releases, publish and run them as described in section 4. Nothing else is needed.

If you ran **pre-release versions** of these migrations from the `develop` branch, your tables are missing columns and indexes that 4.0.0 relies on:

- `carts`: the user column (`cart.users.foreign_key`) is now **nullable**; a new nullable, indexed `session_id` column; unique indexes on (user column, `instance`) and (`session_id`, `instance`).
- `cart_items`: new `original_price`, `original_total_price` and `original_vat` columns; a unique index on (`cart_id`, `row_id`).

Because the pre-release migration versions were never tagged, there is no upgrade migration. Re-publish the two migrations and recreate the tables, for example by rolling back the old ones and migrating again. Carts stored in the pre-release tables are lost, so do this when that is acceptable. Alternatively, write an equivalent `ALTER` migration for your schema.

Behavior of the database driver in 4.0.0:

- **Owner.** Authenticated carts are keyed by `auth()->id()` in the `cart.users.foreign_key` column (the configured name is honored). Guest carts are keyed by the session id in `session_id`. A session id is never written to the user column.
- **Guest to login.** Laravel regenerates the session id on login, so a guest's database cart is not carried over to the user automatically. `CartMergeMiddleware` merges the user's `store()` snapshot. It does not move the guest cart.
- **Writes.** Every write runs in one transaction with a row lock, and cart creation tolerates races. In released 4.0.0, the last committed write wins. The unreleased
  follow-up changes package-managed mutations to lock before reading; see the
  [current concurrency boundary](docs/v4/storage.md).
- **TTL.** `expires_at` is set when the cart row is created. Expired carts are purged when read, and the read cache re-checks the TTL.
- **Round trip.** Timestamps, original prices, applied item coupons and row ids now survive a save and reload, so item coupons and discounts are no longer lost or recomputed.
- **Partial removes.** Removing cart-level coupons, metadata or cart options clears only that column and no longer deletes the cart's items.

---

## Type Changes from Max-Level Static Analysis

`level: max` analysis added native types to signatures that accepted `mixed`, and validation at boundaries that used to accept anything. Most code is unaffected. Check this list if you pass unusual values, extend package classes, or read cart internals.

**Signatures that now reject previously accepted values**

| API | v4.0 type | Before |
|---|---|---|
| `Cart::store()`, `Cart::restore()` `$identifier` | `int\|string` | `mixed` |
| `Cart::associate()` `$model` | `object\|string` | `mixed` |
| `Cart::applyCoupon()` / `addItemCoupon()` `$rowId` | `?string`; `detachCoupon()`: `string` | `mixed` |
| `Cart::applyGlobalCoupon()` | `(string, string, float)` | untyped |
| `Cart::getOptionsByKey()` `$key` | `int\|string` | `mixed` |
| `Cart::update()` second argument | `Buyable\|array\|int\|float\|numeric-string`; numeric strings become numbers, anything else throws `UnexpectedValueException` | stored as given |
| `Cart::add()` `$id` | must be `int\|string`, otherwise `InvalidArgumentException` | `mixed` |
| `CartItem::__construct()` / `fromAttributes()` | `int\|string $id`, `int\|float $qty` and typed attributes | untyped |
| `CartItem::fromArray()` / `updateFromArray()` | values validated; malformed options throw `InvalidArgumentException` | unchecked |
| `CanBeBought::setId()` / `setUrlImg()` | `int\|string` / `string` | untyped |
| `CartStored` / `CartRestored` `$identifier` | `int\|string` | `mixed` |
| `CartItemResource::__construct()` / `CartResource::__construct()` | `CartItem` / `Cart` | untyped |

**Overriding package classes.** Subclasses of `CartItem` that override `setQuantity()` (`: void`), `updateFromBuyable()` (`: void`), `updateFromArray()` (`: void`), `__get()` (`: mixed`) or `getCoupon()` (`: ?object`) must declare the same return types. `CartItem`'s public properties keep PHPDoc-only types so carts serialized by 3.x still load. `CanBeBought::getBuyableIdentifier()`, `getBuyableDescription()` and `getBuyablePrice()` now return `int|string`, `string` and `float`.

**Validation instead of silent coercion**

- `config('cart.format.rounding_mode')` must be a `PHP_ROUND_HALF_*` constant or a `\RoundingMode` case; other values (including `0`, `'1'` or `1.0`) throw `InvalidConfigurationException`, a `CartException`. A `null` `format.decimals` means 2 (it rounded to 0 before).
- Config values of the wrong type throw `InvalidConfigurationException`; numeric strings are still accepted.
- Attribute values read by `CanBeBought`, `HasFiscalCodes` and `HasDiscount` are validated: numbers, numeric strings and `Stringable` values are converted, arrays and objects throw. Plain classes no longer need to declare every optional property.
- `Cart::sum()` and `Cart::weight()` throw on non-numeric values instead of counting them as 0.
- `Cart::toJson()`, `CartItem::toJson()` and `store()` throw `JsonException` when encoding fails.
- `content()` and `getContent()` return a fresh collection keyed by row id (mutating it no longer changes the stored cart) and throw `CartStorageException` if the storage holds anything other than cart items. The database driver refuses to store anything else.

**Bugs fixed on the way.** `add()` with `null` subtitle / fiscal codes / image URL no longer throws a `TypeError`; `getOptions()`, `setOptions()` and `destroy()` no longer throw a `TypeError` on a fresh request with the database driver; `coupons()` no longer nests coupon codes containing dots; `CartItem::applyCoupon()` with an unknown type no longer records the coupon before throwing.

**Testing.** `CartFake` records through a new public `Testing\RecordingDispatcher` (available via `recorder()`), and `assertDispatched()` callbacks are typed per event class.

---

## New Features

### Storage Drivers

Switch between `session` (default) and `database` storage via config:

```php
// config/cart.php
'storage' => [
    'driver' => env('CART_STORAGE_DRIVER', 'session'),
],
```

The `database` driver persists carts across sessions and devices using the `carts` and `cart_items` tables.

### UUID/ULID Support for User Foreign Keys

Configure the owner column of the `carts` table before running its migration:

```php
// config/cart.php
'users' => [
    'foreign_key'      => 'user_id',
    'foreign_key_type' => 'uuid', // 'unsignedBigInteger', 'uuid' or 'ulid'
    'table'            => 'users', // currently not read
],
```

### Guest-to-Auth Cart Merge Middleware

`CartMergeMiddleware` merges the authenticated user's stored cart (`Cart::store($userId)`) into the current cart, once per session. `CartRestoreMiddleware` restores it when the current cart is empty.

```php
// config/cart.php
'middleware' => [
    'merge_on_login' => true,
    'auto_restore'   => false,
],
```

Register the middleware on your routes or the `web` group:

```php
use OfflineAgency\LaravelCart\Http\Middleware\CartMergeMiddleware;

Route::middleware([CartMergeMiddleware::class])->group(function () {
    // ...
});
```

### Cart Serialization

```php
$array    = Cart::toArray();   // Full cart as array
$json     = Cart::toJson();    // Full cart as JSON string
$snapshot = Cart::snapshot();  // toArray() plus an ISO 8601 timestamp
```

### Cart Weight

```php
$weight = Cart::weight(); // Sum of (item option 'weight' * qty)
```

### Cart Metadata

Attach arbitrary key-value metadata to the cart instance:

```php
Cart::setMeta('notes', 'Gift wrap please');
Cart::getMeta('notes');          // 'Gift wrap please'
Cart::meta();                    // ['notes' => 'Gift wrap please']
Cart::forgetMeta('notes');
```

### Cart TTL

Set an expiration for carts of the database driver:

```php
// config/cart.php
'ttl' => 1440, // Minutes from cart creation (null = no expiration)
```

### Configurable Rounding

`format.decimals` and `format.rounding_mode` now apply to every amount the cart computes:

```php
// config/cart.php
'format' => [
    'decimals'           => 2,
    'decimal_point'      => '.',
    'thousand_separator' => ',',
    'rounding_mode'      => PHP_ROUND_HALF_UP,
],
```

### JSON API Resources

```php
use OfflineAgency\LaravelCart\Resources\CartItemResource;
use OfflineAgency\LaravelCart\Resources\CartResource;

return new CartResource(app('cart'));
return CartItemResource::make(Cart::get($rowId));
```

### Macroable Cart

Extend `Cart` at runtime with custom methods:

```php
use OfflineAgency\LaravelCart\Cart;

Cart::macro('totalWithShipping', function (float $shipping): float {
    return $this->total() + $shipping;
});

app('cart')->totalWithShipping(5.99);
// or through the facade: \OfflineAgency\LaravelCart\Facades\Cart::totalWithShipping(5.99);
```

### `CartItem::is(Buyable)`

```php
$item = Cart::get($rowId);
$item->is($product); // true if the id matches and the associated model class is the product's class
```

### `Cart::sum(attribute)`

Sum any numeric attribute across all cart items (raw per-row values, not multiplied by quantity):

```php
Cart::sum('qty');      // Total quantity
Cart::sum('price');    // Sum of the rows' unit net prices
```

---

## Configuration Diff

Publish the latest config (this overwrites your `config/cart.php`, so merge your changes back):

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=config --force
```

```php
// config/cart.php -- new keys in v4

'storage' => [
    'driver' => env('CART_STORAGE_DRIVER', 'session'),
],

'database' => [
    // ...existing keys...
    'carts_table'      => 'carts',       // NEW
    'cart_items_table' => 'cart_items',  // NEW
],

'users' => [
    'foreign_key'      => 'user_id',
    'foreign_key_type' => 'unsignedBigInteger',
    'table'            => 'users',
],

'ttl' => null,

'middleware' => [
    'merge_on_login' => false,
    'auto_restore'   => false,
],

'format' => [
    // ...existing keys...
    'rounding_mode' => PHP_ROUND_HALF_UP, // NEW
],

'limits' => [
    'max_items'        => null,
    'max_qty_per_item' => null,
    'max_total'        => null,
    'min_total'        => null,
],

'tax' => [
    'default_rate'  => 0.0,
    'tax_behavior'  => 'inclusive',
    'fiscal_codes'  => [
        'enabled' => false,
    ],
],
```

---

## Upgrade Checklist

1. Make sure you run PHP 8.4 or 8.5 and Laravel 12.69.2+ or 13.32+.
2. Require `"offline-agency/laravel-cart": "^4.0"` and run `composer update offline-agency/laravel-cart`.
3. Publish and review the config: `php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=config`.
4. If you use fiscal codes, switch from `Buyable` to `FiscalBuyable` + the `HasFiscalCodes` trait.
5. Add `getWeight(): float` to custom `Buyable` implementations (or use `CanBeBought`).
6. Optionally narrow `catch (RuntimeException)` blocks to `CartException` or its subclasses.
7. If you call `store()`, review it: overwriting is now the default, per identifier and instance.
8. If you read `vatBreakdown()`, account for the new `'tax'` and `'tax_name'` keys.
9. Replace `$item->taxRate` with `$item->vatRate`, and deprecated coupon methods with their replacements.
10. If you relied on the Italian VAT labels, publish the translations or set the app locale to `it`: `php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=laravel-cart-lang`.
11. If you keep a `Cart` instance across requests or jobs, or bind a custom `CartStorage`, see section 12 (scoped bindings).
12. If associated models in stored carts are not `Buyable` or Eloquent models, see section 13 (restricted unserialize).
13. If you use the database driver, publish and run its migrations: `php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-database-migrations && php artisan migrate`. If you ran pre-release `develop` migrations, see section 14.
14. Review the type changes listed in "Type Changes from Max-Level Static Analysis".

## Current v4 correctness contracts

See the [maintained v4 documentation](docs/v4/index.md) and
[storage/event guarantees](docs/v4/storage.md). BCMath is a runtime requirement.
Price-only updates mean net base at the retained VAT rate; coupons remain once per row.
Run `composer docs:check` and `composer examples:check` alongside existing quality gates.
Live guest transfer is explicitly enabled; snapshots keep their separate migration path.
