# Configuration

The canonical defaults are [config/cart.php](../../config/cart.php). Configure the
storage driver, connection, `carts_table`, `cart_items_table`, snapshot `table`,
user foreign key column/type, TTL in minutes, format decimals/rounding, tax defaults,
limits and optional guest transfer. Use distinct instances for distinct carts;
instances are not a currency conversion mechanism.

Tax defaults affect new rows without explicit VAT. Inclusive input is gross;
exclusive input is net. Explicit VAT bypasses these defaults. Updates with `price`
always mean net. See [money](money-and-tax.md). `min_total` is a checkout predicate
(`meetsMinimum()`); maximum limits reject mutations.
