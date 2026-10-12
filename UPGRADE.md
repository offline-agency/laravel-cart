# Upgrading from v3 to v4

This guide covers all breaking changes and new features introduced in `offline-agency/laravel-cart` v4.0.0.

---

## Requirements

| Requirement | v3           | v4                   |
|-------------|--------------|----------------------|
| PHP         | >= 8.4       | >= 8.2               |
| Laravel     | 10, 11       | 10, 11, 12           |

---

## Breaking Changes

### 1. Buyable Interface (Getter-Only, New `getWeight()`)

The `Buyable` interface no longer declares setter methods. It is now a **getter-only** contract. A new `getWeight(): float` method has been added.

If your model implemented `Buyable` with custom setters, remove them from the interface implementation. The `CanBeBought` trait still provides setters for convenience.

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

Or use the `CanBeBought` trait, which implements all getters (including `getWeight()`) and still provides setters:

```php
use OfflineAgency\LaravelCart\Contracts\Buyable;
use OfflineAgency\LaravelCart\CanBeBought;

class Product extends Model implements Buyable
{
    use CanBeBought;
}
```

### 2. Fiscal Code Methods Moved to `FiscalBuyable`

The `getVatFcCode()` and `getProductFcCode()` methods have been extracted from `Buyable` into a new `FiscalBuyable` interface (which extends `Buyable`). If your model uses fiscal codes, implement `FiscalBuyable` and use the `HasFiscalCodes` trait.

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
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;
use OfflineAgency\LaravelCart\Traits\HasFiscalCodes;

class Product extends Model implements FiscalBuyable
{
    use HasFiscalCodes;
    // FiscalBuyable extends Buyable, so all getter methods are still required
}
```

### 3. Exception Hierarchy

All package exceptions now extend `OfflineAgency\LaravelCart\Exceptions\CartException` (an abstract class extending `RuntimeException`) instead of extending `RuntimeException` directly. Update any `catch` blocks accordingly.

New exception classes:
- `CartExpiredException` -- thrown when accessing an expired cart
- `CartStorageException` -- thrown on storage read/write failures
- `InvalidDriverException` -- thrown when an unsupported storage driver is configured
- `CartLimitExceededException` -- thrown when cart limits are exceeded (see section 6)

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
use OfflineAgency\LaravelCart\Exceptions\CartException;
use OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException;

try {
    Cart::store($identifier);
} catch (CartAlreadyStoredException $e) {
    // Specific handling
} catch (CartException $e) {
    // Catch-all for any cart exception
}
```

### 4. Storage Drivers (CartStorage Interface)

`Cart` no longer depends on `Illuminate\Session\SessionManager` directly. It depends on the `OfflineAgency\LaravelCart\Contracts\CartStorage` interface. The default driver remains `session`.

**Config change** -- add to `config/cart.php`:

```php
'storage' => [
    'driver' => env('CART_STORAGE_DRIVER', 'session'),
],
```

If you were injecting `Cart` and relying on its `SessionManager` dependency, update to use the `CartStorage` contract:

**Before (v3):**

```php
// Cart constructor accepted SessionManager
$cart = new Cart($sessionManager, $events);
```

**After (v4):**

```php
use OfflineAgency\LaravelCart\Contracts\CartStorage;

// Cart constructor accepts CartStorage
$cart = new Cart($cartStorage, $events);

// Or let the container resolve it (recommended)
$cart = app('cart');
```

To use the database driver, set `CART_STORAGE_DRIVER=database` in your `.env` and publish the migrations:

```bash
php artisan vendor:publish --tag=cart-database-migrations
php artisan migrate
```

### 5. Translations

All user-facing strings now use Laravel's translation system via `__('laravel-cart::cart.*')` keys. Hardcoded Italian labels like `"Iva Inclusa"` and `"Esente Iva"` have been replaced with translation keys.

English and Italian translation files are included. Publish them to customize:

```bash
php artisan vendor:publish --tag=laravel-cart-lang
```

**Before (v3):**

```php
// Hardcoded Italian strings
$item->vatLabel; // "Iva Inclusa" or "Esente Iva"
```

**After (v4):**

```php
// Uses translation keys
$item->vatLabel; // __('laravel-cart::cart.tax_included') or __('laravel-cart::cart.tax_exempt')
// English: "VAT Included" / "Tax Exempt"
// Italian: "IVA Inclusa" / "Esente IVA"
```

### 6. Tax Configuration

A new `cart.tax` config section controls default tax behavior.

**Add to `config/cart.php`:**

```php
'tax' => [
    'default_rate'  => 0.0,          // Auto-applied when no VAT is passed
    'tax_behavior'  => 'inclusive',   // 'inclusive' (EU) or 'exclusive' (US)
    'fiscal_codes'  => [
        'enabled' => false,
    ],
],
```

When `default_rate` is set and no explicit VAT is provided to `Cart::add()`, the rate is applied automatically based on `tax_behavior`:

- **inclusive**: price already includes tax; VAT is extracted from the total price.
- **exclusive**: tax is added on top of the price.

### 7. Cart Limits

A new `cart.limits` config section enables configurable constraints.

**Add to `config/cart.php`:**

```php
'limits' => [
    'max_items'        => null, // Max distinct items in the cart
    'max_qty_per_item' => null, // Max quantity per single item
    'max_total'        => null, // Max cart total (float)
    'min_total'        => null, // Min cart total (float)
],
```

When a limit is exceeded, `Cart::add()` and `Cart::update()` throw `CartLimitExceededException`:

```php
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;

try {
    Cart::add($product);
} catch (CartLimitExceededException $e) {
    $e->limitType;  // e.g. 'max_items'
    $e->limit;      // e.g. 50
    $e->attempted;  // e.g. 51
}
```

### 8. `vatBreakdown()` Returns Additional Keys

`Cart::vatBreakdown()` now returns `'tax'` and `'tax_name'` keys in each breakdown entry alongside the existing keys.

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
//         'tax'      => '22.00',      // NEW (alias for vat)
//         'gross'    => '122.00',
//         'tax_name' => 'VAT',        // NEW (from translation)
//     ]
// ]
```

### 9. `store()` / `restore()` Behavior Changes

`Cart::store()` now **upserts by default** (overwrites existing stored cart). In v3, calling `store()` when a cart was already stored threw `CartAlreadyStoredException`.

**Before (v3):**

```php
Cart::store('user-123'); // Throws CartAlreadyStoredException if already stored
```

**After (v4):**

```php
Cart::store('user-123');                  // Upserts (overwrites) by default
Cart::store('user-123', overwrite: false); // Pass overwrite: false for old behavior
```

`Cart::restore()` now supports merging and keeping the stored copy:

```php
Cart::restore('user-123', mergeOnRestore: true, keepStored: false);
```

### 10. `Cart::fake()` Returns `CartFake`

`Cart::fake()` now returns a `CartFake` instance with testing assertion methods, following the same pattern as `Mail::fake()` and `Queue::fake()`.

**Before (v3):**

```php
// No Cart::fake() available; mocking required
$this->mock(Cart::class, function ($mock) {
    $mock->shouldReceive('add')->once();
});
```

**After (v4):**

```php
use OfflineAgency\LaravelCart\Facades\Cart;
use OfflineAgency\LaravelCart\Events\CartItemAdded;

$cart = Cart::fake();

Cart::add('1', 'Product', '', 1, 9.99);

$cart->assertDispatched(CartItemAdded::class);
$cart->assertNotDispatched(CartItemRemoved::class);
$cart->assertDispatchedTimes(CartItemAdded::class, 1);
```

### 11. `$taxRate` Deprecated in Favor of `$vatRate`

The `CartItem::$taxRate` property is deprecated and will be removed in v5. Use `$vatRate` instead. Both properties currently hold the same value.

**Before (v3):**

```php
$item = Cart::get($rowId);
$rate = $item->taxRate;
```

**After (v4):**

```php
$item = Cart::get($rowId);
$rate = $item->vatRate; // Use vatRate instead
```

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

The `database` driver persists carts across sessions using the `carts` and `cart_items` tables.

### UUID/ULID Support for User Foreign Keys

Configure the user foreign key type for database storage:

```php
// config/cart.php
'users' => [
    'foreign_key'      => 'user_id',
    'foreign_key_type' => 'uuid', // 'unsignedBigInteger', 'uuid', or 'ulid'
    'table'            => 'users',
],
```

### Guest-to-Auth Cart Merge Middleware

Automatically merge a guest cart into the authenticated user's cart on login:

```php
// config/cart.php
'middleware' => [
    'merge_on_login' => true,
    'auto_restore'   => false,
],
```

Register the middleware in your HTTP kernel or route group:

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
$snapshot = Cart::snapshot();   // Array with ISO 8601 timestamp
```

### Cart Weight

Calculate total weight from item options:

```php
$weight = Cart::weight(); // Sum of (option 'weight' * qty) for all items
```

### Cart Metadata

Attach arbitrary key-value metadata to the cart:

```php
Cart::setMeta('notes', 'Gift wrap please');
Cart::getMeta('notes');          // 'Gift wrap please'
Cart::meta();                    // ['notes' => 'Gift wrap please']
Cart::forgetMeta('notes');
```

### Cart TTL

Set an expiration for database-stored carts:

```php
// config/cart.php
'ttl' => 1440, // Minutes (null = no expiration)
```

### Configurable Rounding Mode

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

Use the included JSON resources for API responses:

```php
use OfflineAgency\LaravelCart\Resources\CartResource;
use OfflineAgency\LaravelCart\Resources\CartItemResource;

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

Cart::totalWithShipping(5.99);
```

### `CartItem::is(Buyable)`

Check if a cart item matches a given `Buyable`:

```php
$item = Cart::get($rowId);
$item->is($product); // true if ID and associated model match
```

### `Cart::sum(attribute)`

Sum any numeric attribute across all cart items:

```php
Cart::sum('price');    // Sum of all item prices
Cart::sum('qty');      // Total quantity
Cart::sum('vat');      // Total VAT
```

---

## Configuration Diff

Below is a summary of all new config keys to add when upgrading. Publish the latest config with:

```bash
php artisan vendor:publish --tag=config --force
```

```php
// config/cart.php -- new keys in v4

'storage' => [
    'driver' => env('CART_STORAGE_DRIVER', 'session'),
],

'database' => [
    // ...existing keys...
    'carts_table'      => 'carts',       // NEW
    'cart_items_table'  => 'cart_items',  // NEW
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

1. Update `composer.json` to require `"offline-agency/laravel-cart": "^4.0"`.
2. Run `composer update offline-agency/laravel-cart`.
3. Publish and review the updated config: `php artisan vendor:publish --tag=config`.
4. If using fiscal codes, switch from `Buyable` to `FiscalBuyable` + `HasFiscalCodes` trait.
5. Add `getWeight(): float` to any custom `Buyable` implementations (or use `CanBeBought`).
6. Update `catch` blocks: replace `RuntimeException` with `CartException` or specific subclasses.
7. If storing carts, review `store()` calls -- upsert is now the default.
8. If reading `vatBreakdown()` results, account for new `'tax'` and `'tax_name'` keys.
9. Replace `$item->taxRate` with `$item->vatRate`.
10. Publish translations if you customized VAT labels: `php artisan vendor:publish --tag=laravel-cart-lang`.
11. If using the database driver, publish and run migrations: `php artisan vendor:publish --tag=cart-database-migrations && php artisan migrate`.
