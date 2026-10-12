# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Laravel 13 support (`illuminate/*` `^12.0|^13.0`, `orchestra/testbench` `^10.0|^11.0`)
- Pest `^4.0|^5.0` and PHPUnit 13 schema in `phpunit.xml`
- CI matrix: PHP 8.4/8.5 × Laravel 12/13

### Removed

- Laravel 10 and 11 support. Both are end-of-life and every release is flagged by security advisories, so Composer refuses to install them. Use `^3.x` on Laravel 10/11.

## [4.0.0] - 2026-04-10

### Added

- Translation files (English + Italian) with `laravel-cart` namespace for all user-facing strings
- Generic tax system with configurable `default_rate` and `tax_behavior` (inclusive/exclusive)
- `FiscalBuyable` interface and `HasFiscalCodes` trait for fiscal code support
- `CartStorage` interface with `SessionStorage` and `DatabaseStorage` implementations
- Storage driver pattern (session/database) with `CART_STORAGE_DRIVER` env config
- UUID/ULID support for user foreign keys via `cart.users.foreign_key_type`
- `CartMergeMiddleware` for guest-to-auth cart merge on login
- `CartRestoreMiddleware` for auto-restoring carts from database
- `Cart::toArray()`, `Cart::toJson()`, `Cart::snapshot()` serialization
- `Cart::weight()` for total cart weight calculation
- `Cart::setMeta()`, `Cart::getMeta()`, `Cart::meta()`, `Cart::forgetMeta()` metadata API
- Cart TTL with `expires_at` support in DatabaseStorage
- `cart:prune --expired-only` flag for TTL-based pruning
- Configurable rounding mode via `cart.format.rounding_mode`
- Cart limits & validation (`max_items`, `max_qty_per_item`, `max_total`, `min_total`)
- `Cart::meetsMinimum()` for checkout validation
- `CartLimitExceededException` with `limitType`, `limit`, `attempted` properties
- `CartResource` and `CartItemResource` JSON API resources
- `CartException` abstract base class for exception hierarchy
- `CartExpiredException`, `CartStorageException`, `InvalidDriverException` exceptions
- `Buyable::getWeight()` method
- `Macroable` trait on `Cart` class
- `CartItem::is(Buyable)` comparison method
- `Cart::sum(string $attribute)` aggregation
- `CartFake` testing class with `assertDispatched()`, `assertNotDispatched()`, `assertDispatchedTimes()`
- `Cart::taxName()` method
- New database migrations for normalized `carts` and `cart_items` tables
- `cart-database-migrations` and `laravel-cart-lang` publishable tags
- PHPStan level 6

### Changed

- PHP minimum version lowered from ^8.4 to ^8.2
- `Cart` class now depends on `CartStorage` interface instead of `SessionManager`
- `Buyable` interface is now getter-only (setters remain in `CanBeBought` trait)
- Fiscal code methods moved from `Buyable` to `FiscalBuyable` interface
- All exceptions now extend `CartException` instead of `RuntimeException`
- All user-facing strings use Laravel translation system
- `vatBreakdown()` now returns additional `tax`, `tax_name` keys
- `Cart::fake()` now returns `CartFake` instance
- `totalVatLabel()` returns translated strings ("VAT Included"/"Tax Exempt" in English)
- `formatFloat()` now uses configurable rounding mode and decimal precision

### Deprecated

- `CartItem::$taxRate` — use `$vatRate` instead
- `vatBreakdown()` `'vat'` key — use `'tax'` key instead

### Removed

- `getVatFcCode()`, `setVatFcCode()`, `getProductFcCode()`, `setProductFcCode()` from `Buyable` interface
- Setters from `Buyable` interface (moved to `CanBeBought` trait)
- Hardcoded Italian strings ("Iva Inclusa", "Esente Iva")
