# Memoria upgrade PHP

## PHP 8.6 (2026-11-24)

- **TARGET_PHP:** 8.6 (binario Herd `php86`, 8.6.0). Disponibili anche `php84` (8.4.28) e `php85` (8.5.13).
- **Modalita':** CURRENT. Pacchetto mantenuto di recente, Laravel 12/13 supportano PHP 8.4, CI gia' su 8.4/8.5.
- **SUPPORTED_PHP:** 8.4, 8.5, 8.6 (`~8.4.0 || ~8.5.0 || ~8.6.0`).
- **Esito gate:** VIA LIBERA, nessun bloccante.
- **Framework target:** Laravel 13 (13.43.0). Laravel 12 resta nei vincoli.

### Gate

Risoluzione albero (platform php impostata per versione, estensioni ignorate): PHP 8.4 L12 ok, L13 ok; PHP 8.5 L12 ok, L13 ok; PHP 8.6 L13 ok, L12 ko.

| Pacchetto | Verdetto | Note |
|---|---|---|
| php (`~8.4.0 \|\| ~8.5.0`) | BUMP | aggiunto `~8.6.0` |
| ext-bcmath | OK | inclusa in PHP, caricata in tutti i binari Herd |
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

### Versione

- Ultima stabile: 4.2.0 (GitHub Release 2026-10-19, tag senza prefisso `v`). Il giro precedente di questo task partiva da 4.1.0: nel frattempo e' uscita la 4.2.0 (PR #51), quindi il CHANGELOG "Unreleased" e' stato riallineato.
- NEXT_VERSION: **4.3.0**, MINOR: solo supporto aggiunto (PHP 8.6), nessun minimo alzato, nessun framework rimosso, API pubblica invariata.
- **Meccanismo di rilascio: automazione esistente, nessun `release.yml` aggiunto.** `.github/workflows/release.yml` (con `.github/scripts/release`, `.github/release-config.json`, `.github/RELEASING.md`) al merge su `main` ricalcola la storia first-parent, aspetta il check `CI passed` sul commit di merge e crea tag annotato `X.Y.Z` (senza prefisso `v`) e GitHub Release, una per PR e in ordine. Il bump viene dal titolo Conventional Commit o dalle label `release:*`, con fallback patch: un titolo non conventional avrebbe prodotto 4.2.1, quindi il titolo della PR usa il tipo `feat` (minor = 4.3.0). Il check `Release metadata` lo conferma ("will be released as minor").
- Differenza rispetto al modello del task: le note della release non vengono dalla sezione CHANGELOG ma sono generate dal publisher (PR, commit, bump). Il CHANGELOG non e' letto dal publisher.
- CHANGELOG: sezione `## [4.3.0]` senza data (la data e' quella della GitHub Release); nessuna sezione `[Unreleased]` perche' non aveva voci. La 4.2.0 e' datata 2026-10-19 come da release pubblicata.
- Nessun campo `version` in `composer.json` e nessuna costante di versione nel codice.

### Docs

- Layout: versionato (`docs/v4/`), MINOR quindi aggiornato in place. `docs/README.md` ora e' l'indice delle versioni con compatibilita'. `docs/memory-*.md` e `docs/website-handoff.md` restano nella root di `docs/`. `.oa-docs.json` non esiste.
- Corretta la disponibilita' di release: le parti marcate "unreleased" (payableTaxBreakdown, ricette) sono in 4.2.0; il supporto PHP 8.6 e' marcato non rilasciato (4.3.0).
- Nessun upgrade guide nuovo (solo MAJOR).

### QA

- Strumenti gia' presenti, nessuno aggiunto: Pint (`pint.json`), Larastan/PHPStan livello `max` senza baseline, script `lint`, `lint:check`, `analyse`/`stan`, `test`.
- `phpstan.neon`: `phpVersion` da `80400` a `min: 80400, max: 80699`. PHPStan passa su 8.4, 8.5 e 8.6.
- CI: tests, `composer validate:strict`, Pint check e PHPStan girano gia' su PR e push su `main`; nessun workflow rinominato.

### Badge

Riga: Latest Release, Packagist Version, Total Downloads, PHP Version, Tests, License, poi Codecov, Laravel e PHPStan (esistenti, verificati). Tutti HTTP 200 e senza testi di errore. Rimossi: badge `PHP` statico (sostituito da quello Packagist) e `MIT license` Packagist (sostituito da quello GitHub). Il badge PHP Packagist mostra `~8.4.0 || ~8.5.0` finche' non esce il nuovo tag. Release badge: attivo, esistono release; nessuna release creata da questo task.

### Passi completati e problemi

1. `chore(php): support PHP 8.6` e `fix(cart): pass plain decimal strings to BCMath`: 7 errori PHPStan `argument.type` preesistenti, identici su PHP 8.5 (PHPStan 2.3.7 allarga `(string) $float` a `'INF'|'-INF'|'NAN'|numeric-string`), corretti con `number_format()` come in `TaxAllocator::decimal()`.
2. `ci: add PHP 8.6 to the test matrix` (L12 escluso su 8.6).
3. Docs, README, indice versioni, CHANGELOG 4.3.0, `phpVersion` PHPStan.

### Verifiche (Laravel 13.43.0, E_ALL)

- PHP 8.4.28, 8.5.13, 8.6.0: 670 passati, 2 saltati (test su server DB), 1920 asserzioni, nessuna deprecazione.
- `--prefer-lowest`: PHP 8.4 con L12 (12.71.0) e L13 (13.40.0) 670 passati; PHP 8.6 con L13 lowest 670 passati (informativo, la CI lo esegue solo su 8.4).
- Pint, PHPStan max, `validate --strict`, `audit`, `docs:check`, `examples:check`, test Node, `consumer:check`: pass.
- `test:coverage` non eseguibile in locale (nessun PCOV/Xdebug in Herd); la CI della PR #53 lo ha eseguito con esito positivo.
- `phpstan_turbo` non si carica su PHP 8.6 (warning di startup, PHPStan funziona comunque).

### Branch e follow-up

- Branch: `chore/php-8.6-upgrade`. PR: https://github.com/offline-agency/laravel-cart/pull/53
- Al merge il publisher crea da solo tag e release 4.3.0 (dopo `CI passed` sul commit di merge); badge Latest Release e Packagist si aggiornano da soli. Fallback solo se il job di release fallisce: `gh release view 4.3.0` e, se manca, rilanciare il workflow `Release` con `workflow_dispatch` (`dry_run=false`); in ultima istanza `gh release create 4.3.0 --target main --title "4.3.0" --notes-file <note>`.
- I testi docs/README dicono gia' "4.3.0" per il supporto PHP 8.6: nessun aggiornamento manuale richiesto dopo il rilascio.
- Riabilitare L12 x PHP 8.6 quando il tooling (o Laravel 12) lo permette.
