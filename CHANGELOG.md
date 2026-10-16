# Changelog

## Unreleased

- Declare ext-bcmath and honor configured cart-coupon precision.
- Recalculate base/current price, VAT and gross on array/Buyable updates, retaining row coupons.
- Stage live mutations, make batches/sync atomic, preserve option variants and publish successful events after commit.
- Lock database package mutations before reading; refresh cart-level coupons across owners/TTL.
- Add opt-in trusted guest transfer around login/session regeneration with transactional merge and retries.
- Maintain versioned docs, executable examples and optional Laravel Boost package resources.
- Add MySQL and distribution/documentation/release tooling verification to CI.


All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - Unreleased

See [UPGRADE.md](UPGRADE.md) for the migration guide from 3.x.

### Added

- Laravel 13 support (`illuminate/*` `^12.69.2 || ^13.32`)
- PHP 8.5 support
- Translation files (English + Italian) with `laravel-cart` namespace for all user-facing strings
- Generic tax system with configurable `default_rate` and `tax_behavior` (inclusive/exclusive)
- `FiscalBuyable` interface and `HasFiscalCodes` trait for fiscal code support
- `CartStorage` interface with `SessionStorage` and `DatabaseStorage` implementations
- Storage driver pattern (session/database) with `CART_STORAGE_DRIVER` env config
- Normalized `carts` and `cart_items` tables for the database driver, published with the `cart-database-migrations` tag
- UUID/ULID support for user foreign keys via `cart.users.foreign_key_type`
- `CartMergeMiddleware` to merge the user's stored cart on login
- `CartRestoreMiddleware` to auto-restore the user's stored cart when the current cart is empty
- `Cart::toArray()`, `Cart::toJson()`, `Cart::snapshot()` serialization
- `Cart::weight()` for total cart weight calculation
- `Cart::setMeta()`, `Cart::getMeta()`, `Cart::meta()`, `Cart::forgetMeta()` metadata API
- Cart TTL (`cart.ttl`) with `expires_at` support in `DatabaseStorage`
- `cart:prune --expired-only` flag for TTL-based pruning
- Artisan commands `cart:stats`, `cart:list`, `cart:inspect`, `cart:prune`, `cart:export`, `cart:migrate-driver`
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
- `laravel-cart-lang` publishable tag
- `overwrite` parameter on `Cart::store()` and `keepStored` parameter on `Cart::restore()`
- `cart-legacy-column-migrations` publish tag for apps upgrading from 3.x (adds the `coupons` and `meta` columns)
- `format.rounding_mode` accepts `\RoundingMode` cases; invalid values throw the new `InvalidConfigurationException`
- `use_legacy_events` documented in `config/cart.php`
- Composer scripts `check`, `validate:strict`, `test:parallel`, `test:coverage`, `lint:check`, `analyse` / `stan`
- Test suite can run against PostgreSQL (`CART_TEST_DB_DRIVER=pgsql` and `CART_TEST_DB_*` variables)

### Changed

- Supported platforms: PHP `~8.4.0 || ~8.5.0` and Laravel 12/13. This reverses the earlier lowering of the PHP floor to `^8.2` on `develop`, which was never released. Compared with 3.x, the PHP floor is unchanged at 8.4.
- Every `illuminate/*` component, `nesbot/carbon` and `nunomaduro/termwind` used by `src/` is now a declared dependency
- `symfony/translation` below 7.2 conflicts: those releases (pulled in through Carbon's wide constraint) emit PHP 8.4 deprecations; Laravel 12/13 apps already use 7.x
- `Cart` class now depends on `CartStorage` interface instead of `SessionManager`
- `cart` and `CartStorage` container bindings are scoped (one instance per request / queued job) instead of singletons
- `Buyable` interface is now getter-only (setters remain in `CanBeBought` trait)
- Fiscal code methods moved from `Buyable` to `FiscalBuyable` interface
- All exceptions now extend `CartException` (which extends `RuntimeException`) instead of `RuntimeException` directly
- All user-facing strings use Laravel translation system
- `vatBreakdown()` now returns additional `tax`, `tax_name` keys
- `Cart::fake()` now returns a `CartFake` instance
- `totalVatLabel()` returns translated strings ("VAT Included"/"Tax Exempt" in English)
- Computed amounts use the configurable rounding mode and decimal precision
- `Cart::store()` overwrites an existing stored cart by default (pass `overwrite: false` for the 3.x behavior)
- Content of the `store()` / `restore()` table is written base64-encoded behind an `lcart:b64:` marker; rows written by 3.x are still readable
- `Cart::add()` and `Cart::update()` validate limits on a candidate state: a rejected operation leaves the cart unchanged and dispatches no events
- Database driver schema: nullable user column, new `session_id` column, unique (owner, instance) indexes on `carts`; `original_price`, `original_total_price`, `original_vat` columns and a unique (`cart_id`, `row_id`) index on `cart_items`
- Static analysis: PHPStan (Larastan) at max level over `src`, `config`, `database` and `tests`, without a baseline or ignores. Several signatures gained native types and boundaries validate their input; see UPGRADE.md, "Type Changes from Max-Level Static Analysis"
- `content()` / `getContent()` return a fresh collection keyed by row id
- Dev tooling per framework: Testbench 10.12 / Pest 4.7 / PHPUnit 12.5 on Laravel 12, Testbench 11.3 / Pest 5.3 / PHPUnit 13.4 on Laravel 13; Larastan 3.13 / PHPStan 2.3
- Pint now also checks `src`, `config` and `database`
- CI: a single `CI` workflow with a quality job (strict Composer validation, audit, Pint), a PHP 8.4/8.5 × Laravel 12/13 matrix with highest and lowest dependencies, a PostgreSQL 17 job, one coverage upload (Codecov) and an aggregate `CI passed` check
- Releases: every PR merged into `main` gets its own SemVer tag and GitHub Release after CI passes on its merge commit; the bump comes from `release:*` labels or the Conventional Commit title (see `.github/RELEASING.md`)

### Deprecated

- `CartItem::$taxRate`: use `$vatRate` instead
- `CartItem::$tax`: use `$vat` instead
- `vatBreakdown()` `'vat'` key: use `'tax'` key instead
- `CanBeBought::getBuyableIdentifier()`, `getBuyableDescription()`, `getBuyablePrice()`: use `getId()`, `getName()` / `getSubtitle()`, `getPrice()`

### Removed

- Laravel 10 and 11 support. Both are end-of-life; use `^3.0` on Laravel 10/11.
- `getVatFcCode()`, `setVatFcCode()`, `getProductFcCode()`, `setProductFcCode()` from `Buyable` interface
- Setters from `Buyable` interface (still provided by the `CanBeBought` trait)
- Hardcoded Italian strings ("Iva Inclusa", "Esente Iva")
- `php-coveralls/php-coveralls` dev dependency (coverage goes to Codecov)
- The auto-committing `pint.yml` workflow; style is fixed locally before merge

### Fixed

- Stored cart content on PostgreSQL: raw `serialize()` output contains NUL bytes that text columns reject; content is now encoded (see Changed)
- `restore()` throws `CartStorageException` for content that cannot be decoded, instead of emitting a PHP warning
- Database driver honours `cart.users.foreign_key` instead of a hardcoded `user_id` column
- Database driver stores guest carts under a separate `session_id` column instead of writing the session id into the user column
- Database driver read cache is keyed by owner and re-checks the TTL, so one user's cart is never served to another and expired carts are not returned from cache
- Database driver writes run in one transaction with a row lock, and concurrent cart creation no longer fails on the unique index
- Database driver round trip preserves item timestamps, original prices, applied item coupons and row ids
- Removing cart-level coupons, metadata or cart options with the database driver no longer deletes the cart items
- `applyGlobalCoupon()` persists its discount item
- A rejected `add()` / `update()` (limit exceeded) no longer leaves a partially updated cart or dispatches events
- `cart:list` flags carts whose content cannot be decoded as `? items` instead of `0 items`
- `store()` of a second instance under an identifier that already had a stored instance silently wrote nothing; snapshots are now keyed by identifier and instance, and `restore()` deletes only the snapshot it restored
- The `migrations` publish tag now includes the `coupons` and `meta` column migrations that `store()` writes; published migrations get increasing timestamps so they run in order (same-second names ran `cart_items` before `carts` and `add_*` before `create_*`)
- `cart:prune --expired-only --instance=<name>` now matches carts written by the database driver
- `add()` with `null` subtitle, fiscal codes or image URL no longer throws a `TypeError`
- `getOptions()`, `setOptions()` and `destroy()` no longer throw a `TypeError` on a fresh request with the database driver
- `coupons()` no longer nests coupon codes that contain dots
- `CartItem::applyCoupon()` with an unknown type no longer records the coupon before throwing
- `update()` with a non-numeric quantity throws instead of storing it

### Security

- Decoding of stored cart content restricts `unserialize()` to the classes a cart contains (cart value objects, collections, dates and `Buyable` / Eloquent models); any other class is restored as `__PHP_Incomplete_Class` without running its magic methods
