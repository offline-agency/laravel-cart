# Contributing

Contributions are welcome and are accepted via pull requests against `main`. Please read these guidelines before you open one.

## Development setup

Requirements:

- PHP 8.4 or 8.5 with the `mbstring`, `dom`, `fileinfo` and `pdo_sqlite` extensions (`pdo_pgsql` to run the suite on PostgreSQL)
- Composer 2
- [PCOV](https://github.com/krakjoe/pcov) or Xdebug, only for coverage

```bash
git clone https://github.com/offline-agency/laravel-cart.git
cd laravel-cart
composer update
```

`composer.lock` is not committed, so `composer update` resolves the newest versions allowed by `composer.json`. The test tooling follows the framework line: Laravel 12 resolves Testbench 10 / Pest 4 / PHPUnit 12, and Laravel 13 resolves Testbench 11 / Pest 5 / PHPUnit 13. To work against a specific line:

```bash
composer update --with "laravel/framework:12.*"
composer update --with "laravel/framework:13.*"
```

## Running the checks

| Command | What it does |
|---|---|
| `composer check` | Runs `validate:strict`, `lint:check`, `analyse` and `test` in sequence (CI also runs `composer audit`) |
| `composer test` | Pest test suite (SQLite in memory by default) |
| `composer test:parallel` | Pest in parallel |
| `composer test:coverage` | Pest with coverage; fails below **100%** |
| `composer analyse` (alias `composer stan`) | PHPStan (Larastan) at max level over `src`, `config`, `database` and `tests`, no baseline (see [LARASTAN.md](LARASTAN.md)) |
| `composer lint` | Fix code style with Pint |
| `composer lint:check` | Check code style without changing files |
| `composer validate:strict` | `composer validate --strict` and a strict PSR-4 autoload dry run |

Run `composer check` before you push.

### Running the suite against PostgreSQL

By default the tests use SQLite in memory. Set `CART_TEST_DB_DRIVER=pgsql` to run them against a PostgreSQL server (CI uses PostgreSQL 17). The other variables have the defaults shown:

```bash
CART_TEST_DB_DRIVER=pgsql \
CART_TEST_DB_HOST=127.0.0.1 \
CART_TEST_DB_PORT=5432 \
CART_TEST_DB_DATABASE=laravel_cart_test \
CART_TEST_DB_USERNAME=postgres \
CART_TEST_DB_PASSWORD=postgres \
composer test
```

The database must exist. It is wiped (`db:wipe`) before every test, so use a dedicated database.

For example, with Docker:

```bash
docker run --rm -d --name laravel-cart-pg -p 5432:5432 \
  -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=laravel_cart_test postgres:17
```

### Lowest dependencies

CI also runs every PHP / Laravel combination with the lowest allowed versions, to prove the floors in `composer.json` (`illuminate/*` `^12.69.2 || ^13.32`). To reproduce it locally:

```bash
composer update --prefer-lowest --with "laravel/framework:12.*"
composer analyse && composer test

composer update --prefer-lowest --with "laravel/framework:13.*"
composer analyse && composer test
```

Run `composer update` afterwards to return to the latest versions.

## Code style

The project uses [Pint](https://github.com/laravel/pint) with the `laravel` preset over the whole package (`src`, `config`, `database`, `tests`). CI only **checks** style (`composer lint:check`). No bot commits fixes to your branch, so run `composer lint` locally and commit the result before the PR is merged.

## Pull requests

- **Add tests.** Every change needs tests, and coverage must stay at 100%. Bug fixes should come with a regression test that fails without the fix.
- **Keep static analysis clean.** PHPStan runs at `level: max` with no baseline and no ignores. Fix the types; do not add `@phpstan-ignore`, inline `@var` overrides or casts just to silence an error. See [LARASTAN.md](LARASTAN.md).
- **Update the docs.** If you change behavior or the public API, update `README.md`, `UPGRADE.md` (for breaking changes) and the `Unreleased` / upcoming section of `CHANGELOG.md`.
- **One PR, one topic.** Each merged PR becomes its own release, so keep unrelated changes in separate PRs.
- **Coherent history.** Squash intermediate "fix typo" commits before asking for review.

### PR titles and release labels

Every PR merged into `main` is released automatically as its own SemVer tag (for example `4.1.0`, no `v` prefix) once the `CI passed` check succeeds on the merge commit. The bump comes from the PR's label or title:

| Signal | Bump |
|---|---|
| `release:major` label, a `type!:` / `type(scope)!:` title, or a `BREAKING CHANGE:` footer in the PR body | major |
| `release:minor` label or a `feat:` title | minor |
| `release:patch` label or a `fix` / `docs` / `chore` / `ci` / `build` / `refactor` / `perf` / `test` / `style` / `revert` / `deps` title | patch |
| none of the above | patch |

- Use [Conventional Commits](https://www.conventionalcommits.org/) titles, for example `feat(coupons): add stackable coupons` or `fix(storage): keep row ids on reload`.
- Use at most one `release:*` label. A label must agree with the title. For example, `release:patch` on a `feat:` PR is a conflict.
- The required **Release metadata** check fails on conflicts, so fix the title or labels before merging.

Maintainers: the full policy, admin setup and recovery steps (including `workflow_dispatch` dry runs) are in [.github/RELEASING.md](.github/RELEASING.md).

## Security

Please do not report security vulnerabilities in public issues. Contact the maintainers at info@offlineagency.it instead.

## Current v4 correctness contracts

See the [maintained v4 documentation](docs/v4/index.md) and
[storage/event guarantees](docs/v4/storage.md). BCMath is a runtime requirement.
Price-only updates mean net base at the retained VAT rate; coupons remain once per row.
Run `composer docs:check` and `composer examples:check` alongside existing quality gates.
Live guest transfer is explicitly enabled; snapshots keep their separate migration path.
