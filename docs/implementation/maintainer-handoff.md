# Maintainer handoff — 2026-10-15

## External website: pending, not deployed

No website renderer or deployment source is tracked in this checkout. The live
https://docs.offlineagency.com/laravel-cart/ still exposes the older reference.
Its source repository/hosting credentials are not established by this checkout;
locate the owner of that deployment before publishing. No external project was changed.

Publish `docs/v4/*.md` from this repository as `/laravel-cart/v4/`, with each page
at its corresponding slug (index, installation, configuration, usage, reference,
money-and-tax, coupons, storage, authentication, testing, upgrading, troubleshooting,
agents, alternatives). Link executable examples to this repository at the accepted
release tag. Use `docs/README.md` as the unversioned entry and redirect
`/laravel-cart/` to `/laravel-cart/v4/`. Preserve the old site under a clearly
labelled `/laravel-cart/v3/` historical route after checking its actual API version.

Replace old positional add() examples with the named-argument source in
`examples/quick-start.php`; replace the old multi-argument addCoupon call with
`addCoupon(new CartCoupon(...))`, and distinguish addItemCoupon. Navigation should
expose version selector, installation, money/tax limits, coupons, live storage,
snapshots, authentication and upgrade path. Map old section anchors to corresponding
v4 reference anchors or explicit redirect links; do not redirect old v3 code to a
page claiming unchanged signatures. Keep historical URLs accessible where possible.

Before deployment run composer docs:check and examples:check, then crawl the rendered
site's internal links/anchors and confirm all publish tags and code snippets match the
release. After deployment verify root redirect, version selector, every v4 route,
historical links and the absence of old positional/multi-argument coupon snippets
in current-version pages. Record deployed commit and URL. Local Markdown checks
are not evidence of a live deployment.

## Suggested GitHub settings (review and apply manually)

Description: Laravel shopping cart with VAT/tax, coupons, session and database persistence.
Homepage: https://docs.offlineagency.com/laravel-cart/v4/ only after deployment.
Topics: laravel, shopping-cart, php, vat, tax, coupons, session, persistence.
Review/remove irrelevant dated campaign topics manually; none were changed here.

The 2026-10-15 API snapshot (`protected: false`, empty rulesets) does not establish
that main/tag protection is enforced; detailed branch protection was inaccessible.
Require `CI passed` and `Release metadata` for main, PR review and no force pushes.
Protect numeric release tags from updates/deletion, while allowing the existing
trusted publisher to create tags/releases. Verify fork PR testing remains secret-free.
Do not grant PR jobs write permissions, introduce automatic formatting commits,
or assume GITHUB_TOKEN-created releases trigger another workflow.

## Release review checklist

- Confirm all required matrix/server/docs/consumer/Boost/release-tool checks pass
  on the exact accepted merge SHA; main's moving HEAD is not the target.
- Select a title/label consistent with the actual compatibility decision. The
  proposed additive feature title implies minor; no allocation is hardcoded.
- Keep anchor #44, first-parent order, idempotent markers and retry/resume behavior.
- Run the existing fake-API/temp-git dry-run scenarios; inspect the release plan.
- On a future real merge verify API-accepted merge SHA and post-merge `CI passed`;
  pending/failed/skipped/cancelled/missing checks block publication.
- Never move, overwrite or delete a published tag. No remote writes in this task.
