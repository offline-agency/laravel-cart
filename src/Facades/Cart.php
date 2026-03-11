<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Facades;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Contracts\Couponable;

/**
 * @method static \OfflineAgency\LaravelCart\Cart instance(?string $instance = null)
 * @method static string currentInstance()
 * @method static array<int, CartItem>|CartItem add(mixed $id, mixed $name = null, ?string $subtitle = null, ?int $qty = null, ?float $price = null, ?float $totalPrice = null, ?float $vat = null, ?string $vatFcCode = '', ?string $productFcCode = '', ?string $urlImg = '', array $options = [])
 * @method static CartItem|null update(string $rowId, mixed $qty)
 * @method static void remove(string $rowId)
 * @method static CartItem get(string $rowId)
 * @method static void destroy()
 * @method static Collection<string, CartItem> content()
 * @method static int|float count()
 * @method static bool isEmpty()
 * @method static bool isNotEmpty()
 * @method static CartItem|null first(?Closure $callback = null)
 * @method static Collection<string, CartItem> where(string $key, mixed $value)
 * @method static int uniqueCount()
 * @method static float total(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null)
 * @method static float vat(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null)
 * @method static float subtotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null)
 * @method static float originalTotalPrice(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null)
 * @method static float discount()
 * @method static Collection<int, array{rate: float, net: string, vat: string, gross: string}> vatBreakdown()
 * @method static Collection<string, CartItem> search(Closure $search)
 * @method static void associate(string $rowId, mixed $model)
 * @method static void store(mixed $identifier)
 * @method static void restore(mixed $identifier, bool $mergeOnRestore = false)
 * @method static string totalVatLabel()
 * @method static Collection<string, CartItem> addBatch(array $items)
 * @method static string numberFormat(float|int $value, ?int $decimals, ?string $decimalPoint, ?string $thousandSeparator)
 * @method static array<string, object> coupons()
 * @method static Collection<string, CartCoupon> getCoupons()
 * @method static bool hasCoupon(string $couponCode)
 * @method static void applyCoupon(mixed $rowId, string $couponCode, string $couponType, float $couponValue)
 * @method static void addItemCoupon(mixed $rowId, string $couponCode, string $couponType, float $couponValue)
 * @method static void detachCoupon(mixed $rowId, string $couponCode)
 * @method static bool hasCoupons()
 * @method static bool hasGlobalCoupon()
 * @method static mixed getCoupon(string $couponCode)
 * @method static static removeCoupon(string $couponCode)
 * @method static static removeAllCoupons()
 * @method static static addCoupon(string|CartCoupon|Couponable $coupon)
 * @method static static removeCartCoupon(string $hashOrCode)
 * @method static Collection<string, CartCoupon> listCoupons()
 * @method static bool hasCartCoupon(string $hashOrCode)
 * @method static array<int, string> syncCoupons()
 * @method static static sync(array $items)
 * @method static static addGlobalCoupon(string $couponHash, string $code, string $type, float|int $value)
 * @method static static removeGlobalCoupon(string $couponHash)
 * @method static Collection<string, CartCoupon> getGlobalCoupons()
 * @method static string globalCouponDiscount(string $total)
 * @method static void applyGlobalCoupon(mixed $couponCode, mixed $couponType, mixed $couponValue)
 * @method static array getOptions()
 * @method static void setOptions(array $options)
 * @method static mixed getOptionsByKey(mixed $key, mixed $default_value = null)
 *
 * @see \OfflineAgency\LaravelCart\Cart
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
     *
     * @return \OfflineAgency\LaravelCart\Cart
     */
    public static function fake(): \OfflineAgency\LaravelCart\Cart
    {
        $fake = new \OfflineAgency\LaravelCart\Cart(
            app('session'),
            app('events'),
        );

        static::swap($fake);

        return $fake;
    }
}
