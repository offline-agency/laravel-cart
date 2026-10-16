# Static Analysis (PHPStan + Larastan)

The package is analysed with [PHPStan](https://phpstan.org) and the [Larastan](https://github.com/larastan/larastan) extension at **`level: max`**, with no baseline and no ignored errors.

## Configuration

`phpstan.neon`:

| Setting | Value | Why |
|---|---|---|
| `level` | `max` | The strictest rule level (currently 10). |
| `phpVersion` | `80400` | The minimum supported runtime (PHP 8.4), so code that needs 8.5 is reported. |
| `paths` | `src`, `config`, `database`, `tests` | Shipped code, the published config and migrations, and the test suite including `tests/Fixtures`. |
| `configDirectories` | the package's `config/` | Tells Larastan where the package's config files live, so `env()` calls in `config/cart.php` are recognised as config. |
| `stubFiles` | `tests/phpstan/pest-functions.stub` | Pest declares `$this` in `it()` / `beforeEach()` closures as `TestCall`; at runtime it is the test case. `tests/Pest.php` applies `FeatureTestCase` to the whole suite, and the stub states that type. |

There is no `phpstan-baseline.neon` and no `ignoreErrors`. The exported traits (`CanBeBought`, `HasDiscount`, `HasFiscalCodes`) are analysed through the consumer classes in `tests/Fixtures`, not ignored as unused.

Analysis runs in CI on every PHP 8.4/8.5 × Laravel 12/13 job (highest and lowest dependencies), so the same configuration is verified against Larastan with both framework lines.

## Commands

```bash
composer analyse                          # same as `composer stan`
vendor/bin/phpstan analyse --memory-limit=1G -v
vendor/bin/phpstan clear-result-cache
```

`composer check` runs Composer validation, Pint, PHPStan and the test suite in one go.

## Rules for contributions

- Fix the types instead of hiding errors. Do not add a baseline, `ignoreErrors`, `@phpstan-ignore`, inline `@var` overrides or `assert()` calls, and do not add casts only to make an error go away.
- Use native types where PHP allows them, and generics / array shapes (`Collection<string, CartItem>`, `array{rate: float, ...}`) where it does not.
- Keep `mixed` only at real input boundaries (config values, database rows, decoded JSON, console options, request data) and narrow it right after validation. `OfflineAgency\LaravelCart\Support\Coerce` and `Support\CartConfig` hold the shared narrowing helpers.
- If a genuine PHPStan or Larastan defect blocks a change, add one narrowly scoped `ignoreErrors` entry (identifier, path and message) with a comment linking the upstream issue, and mention it in the pull request.

## Current v4 correctness contracts

See the [maintained v4 documentation](docs/v4/index.md) and
[storage/event guarantees](docs/v4/storage.md). BCMath is a runtime requirement.
Price-only updates mean net base at the retained VAT rate; coupons remain once per row.
Run `composer docs:check` and `composer examples:check` alongside existing quality gates.
Live guest transfer is explicitly enabled; snapshots keep their separate migration path.
