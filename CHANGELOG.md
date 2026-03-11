# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

**Coupon System (v4.1)**
- `Cart::addCoupon(string|CartCoupon|Couponable $coupon): static` — cart-level coupon API with expiry, `minCartAmount`, and duplicate-hash validation.
- `Cart::removeCartCoupon(string $hashOrCode): static` — remove a cart-level coupon by hash or code.
- `Cart::listCoupons(): Collection` — all active cart-level coupons.
- `Cart::hasCartCoupon(string $hashOrCode): bool` — check whether a coupon is applied.
- `Cart::discount(): float` — total discount amount from all cart-level coupons.
- `Cart::syncCoupons(): array` — re-validate all coupons; silently removes invalid ones and returns their codes.
- `Cart::addItemCoupon()` — non-deprecated alias for item-level `applyCoupon()`.
- `CartCoupon` extended with optional constructor params: `?Carbon $expiresAt`, `?int $usageLimit`, `?float $minCartAmount`.
- `CartCoupon` now implements `Couponable` and `JsonSerializable`; new methods: `couponType()`, `isExpired()`, `isApplicableTo()`, `toArray()`, `jsonSerialize()`.
- `src/Enums/CouponType.php` — `Fixed` / `Percentage` backed enum.
- `src/Enums/CartEventType.php` — backed enum for all cart event name strings.
- `src/Contracts/Couponable.php` — interface for coupon objects.
- `src/Traits/HasDiscount.php` — Eloquent model trait implementing `Couponable` via DB columns.
- New exceptions: `CouponAlreadyAppliedException`, `CouponNotFoundException`, `InvalidCouponException`.

**Typed Events**
- `CartItemAdded`, `CartItemUpdated`, `CartItemRemoved`, `CartStored`, `CartRestored`, `CouponApplied`, `CouponRemoved` — `final readonly` event classes in `src/Events/`.
- Config key `use_legacy_events` (default `true`): set to `false` to dispatch only typed events.

**Utility Methods**
- `Cart::isEmpty(): bool`
- `Cart::isNotEmpty(): bool`
- `Cart::uniqueCount(): int` — distinct row count, not sum of quantities.
- `Cart::first(?Closure $callback = null): ?CartItem`
- `Cart::where(string $key, mixed $value): Collection`

**Fiscal / VAT**
- `Cart::vatBreakdown(): Collection` — groups cart items by VAT rate; each entry has `rate`, `net`, `vat`, `gross` (formatted strings). Excludes legacy discount phantom items.
- Per-item `tax_rate` option in `Cart::add()`: pass `options: ['tax_rate' => 10.0]` to override the derived VAT rate for a specific item.
- Config key `rounding_mode` (default `PHP_ROUND_HALF_UP`): controls rounding in `vatBreakdown()`.

**Developer Experience**
- `Cart::fake(): Cart` on the Facade — swaps the container binding to an in-memory instance for tests without a database.
- `cart:clear` Artisan command: `php artisan cart:clear [--force] [--instance=<name>]`.
- Comprehensive `@method` docblocks on `Facades/Cart.php` for IDE autocompletion.

**Store / Restore**
- `Cart::restore(mixed $identifier, bool $mergeOnRestore = false)` — new `$mergeOnRestore` parameter; when `true`, restores by merging into the current session cart (existing rows win).
- Cart-level coupons are serialised to a new nullable `coupons` JSON column on store, and deserialised on restore.
- Migration `0001_00_00_000001_add_coupons_column_to_cart_table.php` adds the `coupons` column.

### Changed

- `Cart::total()` now automatically subtracts cart-level coupon discounts; result is always ≥ 0.
- `CartServiceProvider::boot()` registers `CartClearCommand`.

### Deprecated

- `Cart::applyCoupon(mixed $rowId, ...)` for item-level use — use `Cart::addItemCoupon()` instead.

---

## [4.0.0] - 2026-03-11

### Added

- **Global coupon system**: `addGlobalCoupon()`, `removeGlobalCoupon()`, `getGlobalCoupons()`, `globalCouponDiscount()` methods on `Cart`
- **`CartCoupon`** readonly DTO class (`hash`, `code`, `type`, `value`, `isGlobal`, `isPercentage()`, `isFixed()`)
- **`InvalidCouponHashException`** thrown when referencing a non-existent coupon hash
- **`getCoupons(): Collection`** — returns all per-item coupons as a `Collection<string, CartCoupon>`
- **`hasCoupon(string $couponCode): bool`** — checks whether a coupon is applied to any cart item
- **`removeAllCoupons(): static`** — detaches all per-item coupons and fires `cart.coupons_cleared` event
- **`sync(array $items): static`** — reconciles cart contents to match a given payload (add/update/remove)
- `declare(strict_types=1)` in every PHP source file
- Full typed properties and return types on all `src/` classes
- Pest 3 test suite under `tests/Feature/` (global coupons, per-item coupons, sync, persistence)
- PHP `^8.2` support (broadened from `>=8.3`)
- Laravel 10, 11, 12 support via individual `illuminate/*` packages

### Changed

- **`removeCoupon(string $couponCode): static`** — previously a no-op (called `unset()` on a temporary copy); now correctly detaches the coupon from the owning cart item and fires `cart.coupon_removed`
- `composer.json`: replaced `laravel/framework: ^12.0` with `illuminate/events`, `illuminate/session`, `illuminate/support`, `illuminate/database` (`^10.0|^11.0|^12.0`)
- `CartServiceProvider` is now `final` and uses an explicit factory closure
- PHPStan level raised from 5 to 6 (zero errors, zero blanket suppressions)
- `applyGlobalCoupon()` marked `#[\Deprecated]` — use `addGlobalCoupon()` instead
- Global coupons now persist in the session under `{instance}_global_coupons` (survives across requests)

### Fixed

- `detachCoupon()` on `Cart`: removed dead `unset($this->coupons()[$key])` call that silently did nothing
- `remove()` on `Cart`: no longer routes through the exception-throwing `removeCoupon()` path when clearing a removed item's coupons
- `applyGlobalCoupon()`: explicit `(string)` cast for `Carbon::now()` argument to `md5()` under `strict_types`

### Removed

- `doctrine/dbal` dev-dependency (no longer required)

### Breaking Changes

- `removeCoupon(?string $couponCode)` → `removeCoupon(string $couponCode): static` (non-nullable, returns `static`)
- `Contracts\Buyable`: `getId()` now declares `int|string` return type; `setId()` now declares `int|string $id` parameter type
- PHP `<8.2` is no longer supported
