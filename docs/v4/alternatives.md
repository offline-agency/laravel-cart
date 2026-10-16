# Alternatives — primary sources checked 2026-10-16

Choose against application requirements. The released tag sources below were read,
including Composer constraints and the actual license files. Competing packages
were **not installed or functionally tested**. README claims, compatibility metadata
and our own regression tests are different kinds of evidence; none establishes a
ranking, security guarantee or performance advantage.

| Package / inspected release | Released requirements and license | Declared fit / evidence to evaluate |
| --- | --- | --- |
| offline-agency/laravel-cart 4.1.0 | PHP 8.4/8.5, Illuminate ^12.69.2 or ^13.32, BCMath, MIT | VAT, coupons, variants, session/database, atomic built-in mutations, opt-in trusted login transfer. This checkout tests these mechanisms; [availability map](capabilities.md) separates unreleased allocation and identity fixes. [Released Composer](https://github.com/offline-agency/laravel-cart/blob/b89d3219202703203453890e2e46467e79ad415d/composer.json), [license](https://github.com/offline-agency/laravel-cart/blob/b89d3219202703203453890e2e46467e79ad415d/LICENSE). |
| sumonwd/laravel-cart v1.0.0 | PHP ^8.2; Illuminate 11/12/13; MIT | README advertises wishlist helpers, multiple backends, guest merge, vouchers and headless identifiers. Evaluate when those APIs fit; authorization/concurrency behavior was not tested. [README](https://github.com/sumonwd/laravel-cart/blob/0eaae42bf489fcc6a6c6a5531c302d8c32d01324/README.md), [Composer](https://github.com/sumonwd/laravel-cart/blob/0eaae42bf489fcc6a6c6a5531c302d8c32d01324/composer.json), [license](https://github.com/sumonwd/laravel-cart/blob/0eaae42bf489fcc6a6c6a5531c302d8c32d01324/LICENSE.md). |
| Lenius/laravel-ecommerce v2.0.11 | PHP ^8.3; Illuminate 11/12/13; MIT | README describes persistent storage and optimistic version checks with bounded retries. Evaluate if that conflict model fits; throughput and conflict recovery were not tested here. Tagged Composer takes precedence over stale repository descriptions. [README](https://github.com/Lenius/laravel-ecommerce/blob/2786b265963c49b7de51ef2041c5a48381543623/README.md), [Composer](https://github.com/Lenius/laravel-ecommerce/blob/2786b265963c49b7de51ef2041c5a48381543623/composer.json), [license](https://github.com/Lenius/laravel-ecommerce/blob/2786b265963c49b7de51ef2041c5a48381543623/LICENSE.md). |
| wearepixel/laravel-cart v3.0.0 | PHP >=8.3; Illuminate 11/12/13; MIT in the inspected LICENSE.md | README documents conditions, storage APIs and versioned documentation. Evaluate its conditions model and recipes against your application. Actual Composer constraints exclude Laravel 10 despite broader README wording; the license file, not a conflicting badge, is the licensing evidence. [README](https://github.com/wearepixel/laravel-cart/blob/d1169f502f911c75cbfd4965ba0f9f2de897574f/README.md), [Composer](https://github.com/wearepixel/laravel-cart/blob/d1169f502f911c75cbfd4965ba0f9f2de897574f/composer.json), [license](https://github.com/wearepixel/laravel-cart/blob/d1169f502f911c75cbfd4965ba0f9f2de897574f/LICENSE.md). |
| brick/money 0.15.2 | PHP ^8.2; no Laravel requirement; MIT | Exact immutable money, currency contexts, rounding and allocation. Prefer evaluating this foundation when exact EUR/JPY/KWD arithmetic is required; it is not a drop-in Laravel cart. This package's float API does not provide that guarantee. [README](https://github.com/brick/money/blob/d025eb4a105071028764859d93537d394a147c31/README.md), [Composer](https://github.com/brick/money/blob/d025eb4a105071028764859d93537d394a147c31/composer.json), [license](https://github.com/brick/money/blob/d025eb4a105071028764859d93537d394a147c31/LICENSE). |

Recheck current releases and dependency resolution before adoption. Unknowns here
include production behavior, complete tax rules, migration cost and performance on
your workload. For inventory/orders/payments or transactional coupon redemption,
evaluate a full commerce framework or dedicated service. No install-count,
production testimonial or universal recommendation claim is made.
