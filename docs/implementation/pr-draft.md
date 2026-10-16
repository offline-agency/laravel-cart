# Proposed PR

Title: feat: make cart mutations atomic and add opt-in guest transfer

Suggested label: release:minor

Price updates now recalculate base net/VAT/gross amounts and reapply retained row
coupons. Logical mutations stage a complete candidate, reject invalid states without
partial writes, and publish successful events after commit. Database mutations lock
before reading, so concurrent accepted additions accumulate. An opt-in middleware
captures trusted guest identity before login session regeneration and transfers carts
transactionally with deterministic ownership, pricing and coupon rules.

The change also declares BCMath, adds versioned documentation and executable examples,
ships optional Laravel Boost guidelines/skill, and extends CI with MySQL, documentation,
consumer-install and installed-package Boost checks. Existing release reconciliation
and the 100% coverage gate remain in place.

Validation: PHP 8.4/8.5 and Laravel 12/13 highest combinations; PHP 8.4 lowest
Laravel 12.69.2/13.32.0; PostgreSQL 17.11 and MySQL 8.4.11 independent-process
contention; PHPStan max; 100% coverage; clean dist consumer and Boost 2.10.3;
documentation negative checks; 65 release tests and read-only fake-API dry run.
See the implementation report and evidence for exact versions, skips and commands.

Migration: install BCMath; use update() to change a base price, since adding an existing
row preserves its existing monetary state; keep sync variants distinct by id/options;
enable guest_transfer explicitly and register TransferGuestCart after StartSession,
around the login action. No mandatory custom storage method was added. Invalid coupon
values, duplicate row coupons and contradictory monetary fields now fail explicitly.

Deployment follow-up: the external documentation site and repository protection
settings remain unchanged. Apply the reviewed maintainer handoff separately. The
example 4.1.0 release prediction is not a reserved version or a published tag.
