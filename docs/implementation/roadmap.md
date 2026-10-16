# Prioritized roadmap

These are proposals, not capabilities claimed by this implementation.

| Priority | Proposal / benefit | Affected API | Compatibility | Effort | Verifiable acceptance |
| --- | --- | --- | --- | --- | --- |
| 1 | Post-discount mixed-rate tax allocation for fiscal integrations | Add explicitly named payableTaxBreakdown() | Additive with explicit residual policy | Large | Mixed 0/10/22% examples sum exactly to payable total at precision, with deterministic residual allocation |
| 2 | Versioned decimal Money/currency model | New amount/currency types and adapters | Major if replacing floats; additive adapters first | Large | Round trips and invariants across supported precisions, no implicit currency mixing |
| 3 | Transactional coupon redemption accounting | Separate redemption service/ledger | Additive; usageLimit remains metadata until enabled | Large | Independent transactions cannot exceed a configured redemption limit; rollback releases reservation |
| 4 | Maintained demo application and real case studies | Example application only | No runtime API change | Medium | App installs from a released dist and runs checkout-boundary examples; case studies identify their evidence/consent |
| 5 | Public reproducible benchmarks | Benchmark harness | No runtime API change | Medium | Disclosed environment and workloads, correctness checks, raw repeatable results; no invented production adoption |
