# Contributor guidance

The current task's explicit authorization takes precedence over local confirmation
rules in CLAUDE.md. Preserve unrelated work; use an isolated branch/worktree.

- Runtime: PHP 8.4/8.5, Illuminate ^12.69.2 / ^13.32, ext-bcmath. Keep PHP 8.4 syntax.
- src/ contains runtime code, config/ defaults, database/migrations/ published schemas.
- tests/Regression/ pins defects; tests/Feature/ executes integrations. Server tests
  may wipe only disposable laravel_cart_test* databases. Never application databases.
- docs/v4/ is the canonical reference; examples/ contains executed snippet sources.
  resources/boost/ ships optional third-party guidelines and skill, with no runtime AI.
- Run composer validate:strict, audit --abandoned=report, lint:check, analyse, test,
  test:coverage, docs:check, examples:check and Node release tests. Keep PHPStan max,
  no baseline/blanket ignores, and the existing 100% coverage gate.
- Monetary invariants: base unit amounts survive coupons; discounts apply once per row.
  Net + VAT = gross at precision; cart coupons reduce payable without VAT allocation.
  Stage state before writes; emit successful events after commit, including outer commits.
- No library lockfile is tracked. Keep Pest/PHPUnit/Testbench compatible by Laravel line.
- Release policy: one numeric SemVer tag/release per merged main PR, exact merge SHA,
  post-merge CI gate, ordered/idempotent publisher. Never move tags or bypass failed CI.
  Local implementation authorization does not permit push, merge or publication.
