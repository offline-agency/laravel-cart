## Laravel Cart

`offline-agency/laravel-cart` provides a cart with VAT, row/cart coupons and
session/database persistence for PHP 8.4/8.5 and Illuminate ^12.69.2 or ^13.32.
BCMath is required. Use `OfflineAgency\LaravelCart\Facades\Cart`.

@verbatim
<code-snippet name="Install Laravel Cart" lang="sh">
composer require offline-agency/laravel-cart
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=config
</code-snippet>
<code-snippet name="Add trusted catalog prices" lang="php">
use OfflineAgency\LaravelCart\Facades\Cart;
$item = Cart::add(id: 1, name: 'Widget', qty: 2, price: 10.00, totalPrice: 12.20, vat: 2.20);
Cart::update($item->rowId, ['price' => 20.00]); // net; retains 22% VAT
Cart::total(); // 48.80
</code-snippet>
@endverbatim

Item coupons apply once per row. Price updates change base amounts and reapply
retained coupons. Cart coupons reduce payable total without allocating final VAT;
usageLimit is metadata. Revalidate catalog prices/availability and authorize owners
at checkout. Do not accept payable amounts from browser input.

Session is the default driver. Database carts require `cart-database-migrations`;
`migrations` is for the separate explicit store/restore snapshot table.
Live guest transfer is opt-in via TransferGuestCart after StartSession and before login.
Database live mutations lock from read through commit; custom drivers may offer weaker
guarantees. Successful events follow persistence; outer database transactions defer them.

Canonical docs ship in `vendor/offline-agency/laravel-cart/docs/v4/index.md`.
For detailed integration, use the laravel-cart-development skill.
