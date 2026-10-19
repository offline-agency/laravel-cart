# Reproducible read-path measurements

Cart::getContent() decodes/validates all rows and registers owner-aware references on
each read. searchById() scans that result; total() and payableTaxBreakdown() perform
multiple reads/reductions. These are actual render/API read paths worth measuring.
No optimization or competitor ranking is inferred solely from source inspection.

Run locally from the package checkout with development dependencies:

```sh
composer bench -- --rows=100 --coupons=5 --iterations=10 --samples=3
composer bench:smoke
```

[cart-read.php](../../benchmarks/cart-read.php) seeds outside measurement: 100 distinct
SKU rows, quantities cycling 1/2/3, net 10 / VAT 2.20, and five sequential 1% cart
coupons by default. The fixture is directly seeded into SessionStorage to isolate
reads; it does not measure public mutation throughput. The engine is Laravel's array
session in one CLI process, **not database or Redis performance**.

Each sample reports a fresh Cart object's first call (cold_ns), one unmeasured warm-up,
then a raw warm_total_ns over warm_iterations. Storage reads include cold, warm-up
and timed iterations. This is object-cold/in-process-warm, not an OS/database cold
start. Bootstrap/seed time is separate. Environment, PHP/Laravel version, architecture,
OPcache CLI setting and process peak memory are recorded in the JSON.

[Raw local measurements](../../benchmarks/results/2026-10-16-php85-array-session.json)
were recorded on PHP 8.5.10/Laravel 13.36.0, Darwin arm64, OPcache CLI disabled.
Repeat on your own environment; these observations are not production latency,
throughput guarantees or proof of superiority. No competing package was installed.

CI runs a fixed three-row/one-coupon smoke fixture with independently specified
payable 72.47, validates identity/allocation and checks the JSON shape. It never gates
on unstable wall-clock thresholds. Dataset sizes/iterations are bounded and malformed
CLI sizes fail explicitly. New server contention tests remain separate correctness
evidence, not a performance comparison.
