<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Facades;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use OfflineAgency\LaravelCart\Cart as CartManager;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Contracts\Buyable;
use OfflineAgency\LaravelCart\Contracts\Couponable;
use OfflineAgency\LaravelCart\Storage\SessionStorage;
use OfflineAgency\LaravelCart\Testing\CartFake;

/**
 * @phpstan-import-type CouponSummary from CartManager
 *
 * @method static CartManager instance(?string $instance = null)
 * @method static string currentInstance()
 * @method static array<array-key, CartItem>|CartItem add(mixed $id, mixed $name = null, ?string $subtitle = null, ?int $qty = null, ?float $price = null, ?float $totalPrice = null, ?float $vat = null, ?string $vatFcCode = '', ?string $productFcCode = '', ?string $urlImg = '', ?Carbon $createdAt = null, ?Carbon $updatedAt = null, array<array-key, mixed> $options = [])
 * @method static CartItem|null update(string $rowId, Buyable|array<array-key, mixed>|int|float|numeric-string $qty)
 * @method static void remove(string $rowId)
 * @method static CartItem get(string $rowId)
 * @method static void destroy()
 * @method static Collection<string, CartItem> content()
 * @method static int|float count()
 * @method static bool isEmpty()
 * @method static bool isNotEmpty()
 * @method static CartItem|null first((Closure(CartItem, string): bool)|null $callback = null)
 * @method static Collection<string, CartItem> where(string $key, mixed $value)
 * @method static bool isAlreadyAdded(string|int $id, string $model)
 * @method static CartItem|null searchById(string|int $id, string $model)
 * @method static int uniqueCount()
 * @method static bool meetsMinimum()
 * @method static float total(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null)
 * @method static float vat(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null)
 * @method static float subtotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null)
 * @method static float originalTotalPrice(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null)
 * @method static float discount()
 * @method static Collection<int, array{rate: float, net: string, vat: string, tax: string, gross: string, tax_name: string}> vatBreakdown()
 * @method static Collection<string, CartItem> search(Closure(CartItem, string): bool $search)
 * @method static void associate(string $rowId, object|string $model)
 * @method static void store(int|string $identifier, bool $overwrite = true)
 * @method static void restore(int|string $identifier, bool $mergeOnRestore = false, bool $keepStored = false)
 * @method static string totalVatLabel()
 * @method static string taxName()
 * @method static array<string, mixed> toArray()
 * @method static string toJson(int $options = 0)
 * @method static float sum(string $attribute)
 * @method static float weight()
 * @method static array<string, mixed> snapshot()
 * @method static CartManager setMeta(string $key, mixed $value)
 * @method static mixed getMeta(string $key, mixed $default = null)
 * @method static array<array-key, mixed> meta()
 * @method static CartManager forgetMeta(string $key)
 * @method static Collection<string, CartItem> addBatch(array<array-key, array{id: mixed, name?: mixed, subtitle?: string|null, qty?: int|null, price?: float|null, totalPrice?: float|null, vat?: float|null}> $items)
 * @method static string numberFormat(float|int $value, ?int $decimals, ?string $decimalPoint, ?string $thousandSeparator)
 * @method static array<string, CouponSummary> coupons()
 * @method static Collection<string, CartCoupon> getCoupons()
 * @method static bool hasCoupon(string $couponCode)
 * @method static void applyCoupon(?string $rowId, string $couponCode, string $couponType, float $couponValue)
 * @method static void addItemCoupon(?string $rowId, string $couponCode, string $couponType, float $couponValue)
 * @method static void detachCoupon(string $rowId, string $couponCode)
 * @method static bool hasCoupons()
 * @method static bool hasGlobalCoupon()
 * @method static CouponSummary|null getCoupon(string $couponCode)
 * @method static CartManager removeCoupon(string $couponCode)
 * @method static CartManager removeAllCoupons()
 * @method static CartManager addCoupon(string|CartCoupon|Couponable $coupon)
 * @method static CartManager removeCartCoupon(string $hashOrCode)
 * @method static Collection<string, CartCoupon> listCoupons()
 * @method static bool hasCartCoupon(string $hashOrCode)
 * @method static array<int, string> syncCoupons()
 * @method static CartManager sync(array<int, array{id: mixed, name: string, qty: int, price: float, subtitle?: string, totalPrice?: float, vat?: float, vatFcCode?: string, productFcCode?: string, urlImg?: string, options?: array<string, mixed>}> $items)
 * @method static CartManager addGlobalCoupon(string $couponHash, string $code, string $type, float|int $value)
 * @method static CartManager removeGlobalCoupon(string $couponHash)
 * @method static Collection<string, CartCoupon> getGlobalCoupons()
 * @method static string globalCouponDiscount(numeric-string $total)
 * @method static void applyGlobalCoupon(string $couponCode, string $couponType, float $couponValue)
 * @method static array<array-key, mixed> getOptions()
 * @method static void setOptions(array<array-key, mixed> $options)
 * @method static mixed getOptionsByKey(int|string $key, mixed $default_value = null)
 *
 * @see CartManager
 */
class Cart extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cart';
    }

    /**
     * Swap the Cart implementation with an in-memory instance for testing.
     * Mirrors the Laravel Mail::fake() / Queue::fake() pattern.
     *
     * Usage in Pest / PHPUnit tests:
     *   $cart = Cart::fake();
     *   Cart::add('1', 'Product', null, 1, 9.99);
     *   expect(Cart::count())->toBe(1);
     */
    public static function fake(): CartFake
    {
        $fake = new CartFake(
            new SessionStorage(app('session')),
            app('events'),
        );

        static::swap($fake);

        return $fake;
    }
}
