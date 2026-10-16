# Troubleshooting

- Composer reports ext-bcmath missing: enable BCMath for the PHP binary running
  Composer and the web worker. Do not ignore platform requirements.
- InvalidArgumentException on repricing: supply coherent net/VAT/gross or only net.
  Non-finite values and unsupported coupon domains are rejected before persistence.
- CartLimitExceededException: inspect configured maximums and retry with a valid
  complete batch/sync. No successful mutation events should have escaped.
- QueryException after contention retries: inspect database timeout/deadlock details;
  retry the logical operation after resolving contention. Do not retry committed
  operations merely because an external post-commit listener failed.
- Guest transfer failed: login may continue; listen to GuestCartTransferFailed,
  resolve its cause and retry through the same server-bound pending identity.
- Unknown snapshot/row/coupon: handle the dedicated exceptions listed in the
  [reference](reference.md); authorize ownership, never guess another identifier.
- VAT breakdown differs from payable total: cart coupons have no final VAT allocation.
  See [money and tax](money-and-tax.md).

Unreleased `TaxAllocationException` means payableTaxBreakdown() cannot provide its
documented reconciliation for the supplied amounts, precision or adjustment rows.
Inspect the [supported allocation domain](post-discount-tax.md), revalidate catalog
amounts and handle legacy adjustments explicitly. Do not label the legacy
vatBreakdown() as an equivalent final payable-tax fallback.
