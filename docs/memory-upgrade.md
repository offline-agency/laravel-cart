# Memoria upgrade PHP

## PHP 8.6 (2026-11-24)

- **TARGET_PHP:** 8.6 (binario Herd `php86`, 8.6.0, estensione bcmath caricata)
- **Esito gate:** VIA LIBERA, nessun bloccante.
- **Framework target:** Laravel 13 (13.43.0). Laravel 12 resta nei vincoli (`^12.69.2 || ^13.32`).

### Gate

| Pacchetto | Verdetto | Note |
|---|---|---|
| php (`~8.4.0 \|\| ~8.5.0`) | BUMP | aggiunto `~8.6.0` |
| ext-bcmath | OK | inclusa in PHP |
| illuminate/* (`^12.69.2 \|\| ^13.32`) | OK | 13.43.0 / 12.73.0, `php ^8.3` |
| nesbot/carbon, nunomaduro/termwind | OK | 3.15.1 / 2.4.1 |
| larastan, pint, mockery, phpstan | OK | 3.15.0 / 1.32.3 / 1.6.15 / 2.3.7 |
| orchestra/testbench, pest, pest-plugin-laravel | OK su Laravel 13 | 11.3.0 / 5.3.7 / 5.0.1 |

Nessun pacchetto abbandonato, `composer audit` pulito, estensioni transitive tutte incluse in PHP.

### Laravel 12 su PHP 8.6

- Il resolve del solo runtime riesce (highest e `--prefer-lowest`).
- Il resolve con le dev-deps non riesce: Pest 4 richiede paratest 7.x, le versioni <= 7.24 non ammettono PHP 8.6, le >= 7.25 richiedono PHPUnit 13 che orchestra/testbench-core 10 esclude. Pest 5 e pest-plugin-laravel 5 richiedono Laravel 13.
- Prova forzata (`--ignore-platform-req=php`, solo in scratch): 671 test con deprecazioni e 1 fallito, tutte dal vendor di Laravel 12 (`spl_object_hash()` in `Container.php:1112`, `ArraySessionHandler` senza `create_sid()`).
- Decisione: vincoli invariati, combinazione L12 x PHP 8.6 esclusa dalla CI e documentata come non testata.

### Passi completati

1. `chore(php): support PHP 8.6` (`composer.json`). Nessun bump di dipendenze, nessun cambio di framework. `composer.lock` non e' tracciato.
2. `fix(cart): pass plain decimal strings to BCMath`. Preesistente, non legato a PHP 8.6: PHPStan 2.3.7 allarga `(string) $float` a `'INF'|'-INF'|'NAN'|numeric-string` (7 errori `argument.type` in `src/Cart.php`, identici su PHP 8.5). Corretto con `number_format()`, come in `TaxAllocator::decimal()`, senza ignore ne' baseline.
3. `ci: add PHP 8.6 to the test matrix`: PHP 8.6 x Laravel 13, L12 escluso. Job quality/postgres/mysql restano su PHP 8.5.
4. `docs: document PHP 8.6 support`: README, docs/v4, UPGRADE, CONTRIBUTING, LARASTAN, AGENTS, Boost guideline, CHANGELOG (Unreleased, nessuna versione assegnata).

### Verifiche su PHP 8.6.0 (Laravel 13.43.0)

- Pest con `error_reporting=E_ALL`: 670 passati, 2 saltati (test server DB, saltati anche prima), 1920 asserzioni, nessuna deprecazione.
- Pint pass; PHPStan max pass (nessun baseline/ignore); `validate:strict`, `audit`, `docs:check`, `examples:check`, test Node e `consumer:check` pass.
- `test:coverage` non eseguibile in locale: nessun PCOV/Xdebug nei binari Herd. Lo verifica il job di coverage in CI.
- Problema: l'estensione `phpstan_turbo` non si carica su PHP 8.6 (warning di startup, PHPStan funziona comunque).

### Branch e follow-up

- Branch: `chore/php-8.6-upgrade`. PR: https://github.com/offline-agency/laravel-cart/pull/53 (non draft).
- Verificare in CI che `shivammathur/setup-php` fornisca PHP 8.6 e che il coverage job resti al 100%.
- Quando il tooling lo permette (Pest 4 / paratest / Testbench 10 con PHP 8.6, oppure Laravel 12 senza deprecazioni), riabilitare L12 x PHP 8.6.
- Nessuna versione/tag assegnata: la release segue la policy del repository.
