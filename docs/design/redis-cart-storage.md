# Proposal: dedicated Redis cart storage

Status: design only; no redis driver setting/class is shipped by this proposal.
Redis-backed Laravel sessions serialize the whole session at request end and share
its TTL. A dedicated driver would store owner/instance cart state independently,
with its own TTL and atomic mutation/revision boundary.

## Proposed public surface

```php
// Proposed, not currently callable:
config(['cart.storage.driver' => 'redis', 'cart.redis.ttl_seconds' => 7200]);
final class RedisStorage implements AtomicCartStorage { /* existing interface methods */ }
```

Keep CartStorage compatibility. Implement atomic(instance, operation) by reading a
versioned aggregate (items, coupons, options, meta, timestamps), staging a detached
candidate, and committing with a small Lua CAS script comparing the revision. Retry
from fresh state at most three times; throw a specific retriable conflict on exhaustion.
Only publish events after accepted CAS, never per attempt. SQL outer transactions
cannot roll back a Redis commit; document this boundary and use an application outbox
where distributed coordination is required. Reject unsupported atomic nesting rather
than pretending Redis participates in arbitrary SQL transactions.

## Keys, lifecycle and serialization

Use a configured namespace plus hashed tenant/owner type + owner ID + instance,
never arbitrary browser owner keys. Cache context must include owner/instance; use
request-scoped bindings. State includes schema version, revision and expiresAt. Define
fixed vs sliding TTL explicitly, update TTL in the same CAS, and treat expired state
as empty. Avoid unsafe PHP object deserialization; reuse a versioned validated codec
with explicit schema migration and bounded payload/amount limits.

Redis Cluster hash tags must put all keys for one aggregate in one slot; prefer one
aggregate key. Guest-to-user transfer crosses owner slots: either explicitly co-locate
an application partition and atomically CAS both keys in one script, or implement a
durable idempotent transfer workflow. Neither choice silently inherits database
transfer guarantees. Test cluster constraints, expiry during retry and source deletion
only after accepted persistence. Do not advertise cross-slot atomic Lua operations.

## Risks, tests and scope

Primary risks: retry storms, eviction/data loss, clock/TTL differences, codec version
skew, long-running workers, large scripts/payloads and two-owner transfer failure.
Keep credentials/TLS/client choice in Laravel configuration; choose an explicit
supported Redis client dependency/extension only during implementation.

Acceptance requires real disposable Redis tests with two processes and barriers:
different adds, same-row increments, stale revision retries/exhaustion, TTL isolation
from sessions, rollback/no duplicate events, owner changes, malformed state, replayed
transfer and Cluster CROSSSLOT behavior. Mock Redis is insufficient locking evidence.

Estimated scope: **large**, preferably separate reviewable changes for codec/driver,
contention/TTL and guest transfer/cluster support. This is an additive driver, but any
change to global CartStorage/event contracts needs a separate compatibility decision.
