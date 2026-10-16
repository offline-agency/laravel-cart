# Installation

Development documentation: see the [capability map](capabilities.md) for released
features and unreleased additions.

Requirements: PHP `~8.4.0 || ~8.5.0`, BCMath (`ext-bcmath`), and the declared
Illuminate components `^12.69.2 || ^13.32`. PHP 8.3 is excluded by package policy,
not an upstream EOL claim. PHP prereleases are excluded. Composer checks extensions
before runtime: install your OS PHP BCMath package (for example `php8.4-bcmath`)
or enable `bcmath` in your PHP image and verify `php --ri bcmath`.

```sh
composer require offline-agency/laravel-cart
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=config
```

Provider and facade discovery are automatic. Default live storage is session;
no package tables are needed for this driver. For live database carts:

```sh
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-database-migrations
php artisan migrate
```

For a custom database connection, run migrations with `php artisan migrate --database=your_connection`
(the configured table names/owner schema are read by the migrations).
Set `cart.storage.driver` to `database`. Explicit `store()`/`restore()` snapshots
use a separate `cart` table: publish tag `migrations` for a fresh install, or
`cart-legacy-column-migrations` for existing v3 snapshot tables. Never run both
create migrations on an already-created snapshot table. Translations use
`laravel-cart-lang`. Configure owner types and table names before publishing/running migrations.
