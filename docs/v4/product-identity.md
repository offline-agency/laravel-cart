# Product identity (unreleased lookup fix)

Product keys and authenticated owner keys are separate contracts. Product IDs are
`int|string`. Canonical identity is **exact string conversion**, the same input
used by row-ID hashing: integer 42 and string `"42"` match. `"042"`, `"42-blue"`,
`"4.2e1"`, whitespace/case variants, SKUs, UUIDs and ULID-like values are not coerced
to numbers. Very large decimal strings remain strings, without integer overflow.
This is not a Unicode/case/whitespace-normalizing identifier service.

`isAlreadyAdded($id, $model)` and `searchById($id, $model)` now use this rule, as does
`CartItem::is($buyable)`. Previously integer casts or PHP loose comparisons could
alias different IDs. If you relied on `"042"` finding product 42, pass the actual
canonical key instead. The correction aligns lookup with existing row identity;
it does not rename stored rows or require a database migration.

Legacy creation still rejects empty IDs, including integer 0 and string `"0"`.
Zero lookups now return no match instead of aliasing an arbitrary SKU. This task
preserves that input-domain behavior. Do not infer the product-ID contract from
UUID/ULID **owner** configuration.

Row identity additionally includes sorted options: two sizes of one product are
distinct rows. Model search returns the first matching product/model across its
variants; it is not a unique variant lookup. Use rowId for updates/removals. Sync
already uses actual row identities and needs no numeric-ID coercion change.

The legacy public `model` field may contain a class string, object or model ID.
Association does not automatically load/authorize a product. The existing magic
resolver forwards the unchanged key to find() when invoked; application models
must configure their string primary key or explicitly query a SKU column. Prefer
a trusted application catalog lookup over exposing a browser-selected model class.

[ProductIdentityTest.php](../../tests/Regression/ProductIdentityTest.php) covers
public lookups, Buyable comparison, zero, leading zeros, large IDs, SKU/UUID/ULID,
variants/sync and model lookup on both session/database drivers and reloads.
