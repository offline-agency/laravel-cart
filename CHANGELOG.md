# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
