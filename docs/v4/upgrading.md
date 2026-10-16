# Upgrading

See [UPGRADE.md](../../UPGRADE.md) for v3 -> v4 migration, positional API changes,
owner migrations and snapshots. This compatible v4 extension requires BCMath to be
installed explicitly. Repricing now changes the base and reapplies coupons; use
`update($rowId, ['price' => 20.0])` instead of editing item properties. Item coupon
allocation remains once per row. Sync variants use product/options identity; reject
duplicate identical rows before calling sync. Database mutations preserve concurrent
accepted changes; direct put remains replacement. Guest transfer requires explicit opt-in.
