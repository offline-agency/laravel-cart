<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Examples;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;

/** Application policy example; does not reserve inventory, coupon usage or payment. */
final class CheckoutValidation
{
    /**
     * @param  Closure(CartItem): array{net: float, vat: float, gross: float, available: int}  $catalog
     * @param  Closure(string, int|string): CartCoupon  $eligibleCoupon  Trusted customer/campaign policy, not request data.
     */
    public function __construct(private readonly Cart $cart, private readonly Closure $catalog, private readonly Closure $eligibleCoupon) {}

    public function applyCoupon(string $code): void
    {
        $owner = $this->owner();
        if (! preg_match('/\A[A-Z0-9_-]{1,40}\z/', $code)) {
            throw ValidationException::withMessages(['coupon' => 'Select a valid coupon code.']);
        }
        // docs:coupon:start
        // This resolver checks current campaign/customer eligibility using server data.
        $coupon = ($this->eligibleCoupon)($code, $owner);
        $this->cart->addCoupon($coupon); // Package checks definition, expiry and gross minimum.
        // docs:coupon:end
    }

    public function validateForCheckout(): void
    {
        $owner = $this->owner();
        if ($this->cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Add products before checkout.']);
        }
        // docs:checkout:start
        // Reject stale state without partially repricing the cart or reserving stock.
        foreach ($this->cart->content() as $item) {
            if (! is_finite((float) $item->qty) || $item->qty <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Checkout requires positive finite product quantities.']);
            }
            $current = ($this->catalog)($item);
            if ($item->qty > $current['available']) {
                throw ValidationException::withMessages(['quantity' => 'Availability changed. Refresh the cart.']);
            }
            foreach ([[$item->originalPrice, $current['net']], [$item->originalVat, $current['vat']], [$item->originalTotalPrice, $current['gross']]] as [$saved, $trusted]) {
                if (number_format($saved, 4, '.', '') !== number_format($trusted, 4, '.', '')) {
                    throw ValidationException::withMessages(['cart' => 'Catalog prices changed. Refresh the cart.']);
                }
            }
        }
        $gross = (float) $this->cart->numberFormat($this->cart->total() + $this->cart->discount(), null, '.', '');
        foreach ($this->cart->listCoupons() as $stored) {
            $current = ($this->eligibleCoupon)($stored->code, $owner);
            if ($current->isExpired() || ! $current->isApplicableTo($gross) || $current->toArray() !== $stored->toArray()) {
                throw ValidationException::withMessages(['coupon' => 'Coupon eligibility changed. Remove and reapply it.']);
            }
        }
        // docs:checkout:end
    }

    private function owner(): int|string
    {
        $id = auth()->id();
        if (! is_int($id) && ! is_string($id)) {
            throw new AuthorizationException('Authenticate before applying customer coupons or checking out.');
        }

        return $id;
    }
}
