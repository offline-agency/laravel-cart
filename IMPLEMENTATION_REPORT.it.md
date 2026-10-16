# Consegna — Laravel Cart, 16 ottobre 2026

## Base e risultato

Base verificata: `main` remoto, SHA `60eb1006c9c2b31ff50cebd7b8b677a1dd16cc16`,
versione stabile `4.0.3`. Lavoro isolato nel branch `feat/cart-correctness-v4`,
worktree `/private/tmp/laravel-cart-correctness`. Il checkout originale e il suo
`PR.md` non tracciato sono stati preservati. Nessun push, PR remota, merge,
tag/release di produzione, pubblicazione del sito o modifica amministrativa.

I percorsi principali modificati sono `src/Cart.php`, `src/CartItem.php`,
`src/CartCoupon.php`, `src/Storage/`, `src/Http/Middleware/TransferGuestCart.php`,
`config/cart.php`, `composer.json` e `.github/workflows/ci.yml`.

Le mutazioni preparano uno stato separato, verificano il risultato completo e
persistono prima degli eventi. Il database blocca proprietario/istanza prima della
lettura e ritenta al massimo tre volte gli errori di concorrenza riconosciuti da Laravel.
Gli eventi aspettano anche il commit di una transazione applicativa esterna.
Il trasferimento guest è disabilitato per default: cattura l'identità fidata prima
della rigenerazione della sessione e mantiene la sorgente se il merge fallisce.
Il login continua, con eccezione/evento di fallimento osservabile e possibilità di retry.

## Riscontri e regressioni

Baseline effettiva: `composer test`, exit 0, 565 test e 1.524 asserzioni.
Le prime nuove regressioni hanno prodotto 12 fallimenti e 14 asserzioni prima
delle correzioni; gli esiti principali sono riportati nella tabella seguente.

| Riscontro | Prima | Dopo / classificazione |
| --- | --- | --- |
| P0: requisito aritmetico e precisione | Manifest senza ext-bcmath; sconto 0,12 invece di 0,124 | Corretto: requisito diretto; precisione configurata; consumer senza estensione rifiutato da Composer prima del runtime |
| P0: aggiornamento prezzo | Base rimasta 10 anziché 20; riga con coupon 23,18 anziché 46,36 | Corretto su entrambi i driver, Buyable, risorse e reload; netto 20, IVA 4,40, lordo 24,40 nel caso richiesto |
| P0: batch/sync parziali | Batch rifiutato lasciava un elemento; sync perdeva la riga rimossa | Corretto: stato completo e riferimenti preservati, nessun evento di successo; anche errore SQL dopo delete |
| Concorrenza reale | Due incrementi lasciavano quantità 2 anziché 3 su entrambi i server | Corretto: processi/connessioni distinti e barriera esplicita; aggiunte diverse conservate, quantità 3, eventi una sola volta |
| Cambio opzioni con quantità zero | InvalidRowID o sovrascrittura della variante di destinazione | Corretto: rimozione della sorgente prima della ricodifica |
| Riferimenti, cambio owner e TTL | Ulteriori regressioni hanno trovato riferimenti obsoleti dopo rekey e collisione fra ID user/session | Corretto; rilettura coupon e contesto owner distinto; suite dedicata |
| Login con sessione rigenerata | Funzione live guest non presente; middleware storico ripristinava snapshot | Aggiunta opt-in, test HTTP di login reale, retry, rollback esterno, UUID/ULID, tabelle/connessioni personalizzate |
| Coupon in notazione scientifica | Tre ValueError BCMath riprodotti con valori finiti | Corretto: espansione decimale senza perdere coefficienti; quattro regressioni verdi |
| Correzioni v4 preesistenti | Rimozione coupon, owner configurabili, TTL, rollback persistenza, hydration, ordine migrazioni | Conservate e verificate dalle suite precedenti; nessuna riscrittura duplicata |
| Ipotesi non riprodotte autonomamente | Non tutte le osservazioni storiche sono difetti ancora presenti | Nessuna nuova pretesa di riproduzione per le correzioni già presenti; baseline verde conservata |

Le regressioni coprono anche la contesa PostgreSQL/MySQL e la notazione scientifica.
La revisione indipendente del codice ha segnalato
casi aggiuntivi poi corretti; l'ultima revisione snapshot non ha rilevato problemi importanti.

## Contratti e compatibilità

Raccomandazione: **minor**, per la nuova integrazione opt-in e la capacità opzionale
`AtomicCartStorage`. L'interfaccia obbligatoria `CartStorage` resta invariata; il
coupon di articolo continua ad applicarsi una sola volta per riga. Non sono cambiati
i rami supportati: PHP 8.4/8.5, Laravel 12/13. Le pagine ufficiali PHP/Laravel/Boost
sono state ricontrollate durante il lavoro; il cambio data al 16 ottobre non modifica
questa politica. Nessun lockfile di libreria aggiunto, requisito piattaforma ignorato
o blocco advisory disattivato.

Migrazione concreta:

```php
// Updating the base price retains the explicit VAT rate and reapplies row coupons.
$cart->update($item->rowId, ['price' => 20.00]);

// Opt in only after registering the middleware described in authentication.md.
config(['cart.guest_transfer.enabled' => true]);
```

Installare BCMath. Una nuova add sulla stessa riga conserva base/coupon esistenti e
incrementa la quantità: usare update per riprezzare. Sync identifica varianti tramite
id e opzioni ordinate; duplicati ambigui vengono rifiutati. Importi non finiti,
coupon malformati/duplicati e campi netto/IVA/lordo contraddittori ora falliscono
esplicitamente. Gli eventi spostati dopo commit costituiscono la correzione del
contratto di successo, da considerare se un listener dipendeva dal vecchio timing.

## Verifiche finali

Esiti locali, non risultati di una nuova esecuzione GitHub Actions. Tutti i controlli
finali elencati hanno exit 0, salvo il tentativo nativo di coverage senza driver
descritto sotto.

| Comando / ambiente | Esito |
| --- | --- |
| composer validate:strict | Manifest e autoload ottimizzato strict validi |
| composer audit --abandoned=report | Nessun advisory |
| composer lint:check | Pint verde |
| composer analyse | PHPStan 2.3.1 / Larastan 3.13.0, level max, 134 file, nessun errore |
| composer test, PHP 8.5.10 / Laravel 13.36.0 / SQLite | 636 passati, 2 saltati, 1.796 asserzioni |
| Coverage PHP 8.4 container PCOV, pest --coverage --min=100 | 100%, senza esclusioni o riduzione del denominatore |
| PostgreSQL 17.11, suite completa | 636 passati, 2 saltati, 1.814 asserzioni; contesa reale inclusa |
| MySQL 8.4.11, suite completa | 637 passati, 1 saltato, 1.816 asserzioni; contesa reale inclusa |
| composer docs:check | Link, anchor, publish tag e snippet generati validi |
| composer examples:check | Fonte reale quick-start eseguita sui due driver, 12 asserzioni |
| node --test scripts/test/*.test.mjs | 4 test negativi: link, anchor, publish tag, snippet obsoleto |
| node scripts/consumer-smoke.mjs | Archive/install --no-dev; requisito BCMath; coupon fissi/percentuali a 2/3 decimali; nessuna dipendenza dev del package |
| Stesso consumer, Boost 2.10.3 opzionale | Risorse distribuite, discovery reale, Blade renderizzato, skill installata |
| node --test .github/scripts/release/test/*.test.mjs | 65 test passati |
| actionlint 1.7.7 in container | Tutti i tre workflow validi |
| git diff --check | Nessun errore |

Il comando nativo `composer test:coverage` ha inizialmente exit 1 per assenza di un
driver; la verifica equivalente con PHP 8.4 e PCOV nel container è passata.
I tentativi bloccati dai socket del sandbox (PHPStan/database/Docker) sono stati
rieseguiti con autorizzazione: non sono stati conteggiati come test applicativi riusciti.
SQLite salta i due test di locking server; PostgreSQL salta fixture legacy con JSON
malformato/NUL serializzati; MySQL salta il JSON malformato rifiutato dal database.
Il requisito BCMath assente è simulato con `config.platform.ext-bcmath=false` nel
consumer: è un vero rifiuto Composer, non la disinstallazione dell'estensione host.

Matrice supplementare, test completi e PHPStan entrambi exit 0:

| PHP | Laravel | Dipendenze | Testbench / Pest / plugin / PHPUnit |
| --- | --- | --- | --- |
| 8.4.25 | 12.70.0 | highest | 10.12.0 / 4.7.8 / 4.1.0 / 12.5.33 |
| 8.5.10 | 12.70.0 | highest | 10.12.0 / 4.7.8 / 4.1.0 / 12.5.33 |
| 8.4.25 | 13.36.0 | highest | 11.3.0 / 5.3.2 / 5.0.1 / 13.4.2 |
| 8.4.25 | 12.69.2 | lowest | 10.12.0 / 4.7.8 / 4.1.0 / 12.5.33 |
| 8.4.25 | 13.32.0 | lowest | 11.3.0 / 4.7.8 / 4.1.0 / 12.5.33 |

La sesta combinazione highest è la suite principale PHP 8.5/Laravel 13. Nessuna
pretesa di lowest su PHP 8.5. Composer 2.10.2; la tabella riporta le risoluzioni effettive.

## Dry run del rilascio

Nessun candidato realmente mergiato. `node scripts/release-dry-run.mjs` usa il
trasporto fake-API/temp-git esistente; exit 0, zero scritture remote.
Titolo candidato: `feat: make cart mutations atomic and add opt-in guest transfer`,
label `release:minor`; precedente reale osservato 4.0.3; previsione **4.1.0**.
Target fixture esatto `686eeaaf79c0c12f9c8eceeb12e523ddaaca3d01`, check fixture
`CI passed: success`, run 1027. Tag/release solo pianificati sulla fixture.

La versione non è prenotata. Un futuro merge reale deve verificare SHA accettato
dall'API e CI post-merge su quello SHA; pending/failure/skipped/cancelled/missing
bloccano il publisher. Restano riconciliazione ordinata, anchor #44, idempotenza,
nessuno spostamento di tag. I test Node sono ora anche nel gate CI dello SHA main.

## Documentazione, consegna esterna e limiti

Fonte mantenuta: [docs/v4/index.md](docs/v4/index.md), reference trasferita dal README,
installazione/configurazione, API, denaro/IVA, coupon, storage, autenticazione,
testing, upgrade, troubleshooting, agenti e alternative datate. README abbreviato;
UPGRADE, CHANGELOG, CONTRIBUTING, LARASTAN e SECURITY allineati. Crediti/MIT conservati.
Fonte eseguibile: `examples/quick-start.php`; il checker confronta gli snippet con
quella fonte, senza eseguire Markdown arbitrario. Le ricette frontend sono illustrative.

Boost: `resources/boost/guidelines/core.blade.php` e
`resources/boost/skills/laravel-cart-development/SKILL.md`. Nessuna dipendenza AI runtime.
`AGENTS.md` raccoglie comandi e invarianti. I dieci scenari neutrali in
`docs/v4/agents.md` sono una ricetta manuale, non una campagna eseguita o un ranking.

**Sito esterno non pubblicato e amministrazione non modificata.** Il repository
non contiene renderer/deployment del sito pubblico; proprietà del progetto esterno
non accertata. Restano da applicare manualmente pubblicazione delle sorgenti v4,
route corrente e storico verificato, sostituzione degli esempi obsoleti, redirect,
navigazione e verifica link, oltre alle impostazioni GitHub e alla protezione main/tag.
Lo snapshot API non dimostra protezioni attive.

Limiti intenzionali: vatBreakdown rimane precedente ai coupon di carrello; usageLimit
è metadata; nessun ledger riscatti, inventario, pagamento, certificazione fiscale o
Money multivaluta. Driver personalizzati e scritture raw non acquisiscono automaticamente
transazioni/merge concorrenti. La sessione richiede blocking applicativo per richieste
parallele; memoria sessione e rollback SQL esterno successivo non sono una transazione
distribuita. Listener che falliscono dopo commit non annullano la scrittura.
Il checkout deve autorizzare owner/snapshot e rivalidare prezzi/disponibilità affidabili.

La [PR #50](https://github.com/offline-agency/laravel-cart/pull/50) è stata aperta
su successiva autorizzazione dell’utente. Le proposte future comprendono breakdown
fiscale post-sconto, Money/currency versionato, ledger riscatti, demo/casi reali e
benchmark riproducibili.
