<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Examples\CheckoutValidation;

beforeEach(function (): void {
    $file = __DIR__.'/../../examples/CheckoutValidation.php';
    if (is_file($file)) {
        require_once $file;
    }
});

it('validates authoritative catalog and customer coupons without trusting request amounts', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $this->actingAs(new GenericUser(['id' => 7]));
    $cart = resolveCart();
    $cart->add(id: 'sku-red', name: 'Server product', qty: 2, price: 10.0, totalPrice: 12.2, vat: 2.2);
    $calls = [];
    $validation = new CheckoutValidation($cart,
        fn (): array => ['net' => 10.0, 'vat' => 2.2, 'gross' => 12.2, 'available' => 5],
        function (string $code, int|string $owner) use (&$calls): CartCoupon {
            $calls[] = [$code, $owner];

            return new CartCoupon(hash: 'campaign-v1', code: $code, type: 'fixed', value: 2.0, usageLimit: 1);
        });
    $validation->applyCoupon('MEMBER');
    $before = serialize($cart->toArray());
    $validation->validateForCheckout();
    expect($calls)->toBe([['MEMBER', 7], ['MEMBER', 7]])->and($cart->total())->toBe(22.4)
        ->and(serialize($cart->toArray()))->toBe($before);
})->with(['session', 'database']);

it('rejects stale catalog prices or unavailable stock before checkout', function (float $net, float $vat, float $gross, int $available): void {
    $this->actingAs(new GenericUser(['id' => 7]));
    $cart = resolveCart();
    $cart->add(id: 1, name: 'One', qty: 2, price: 10.0, totalPrice: 12.2, vat: 2.2);
    $validation = new CheckoutValidation($cart, fn (): array => ['net' => $net, 'vat' => $vat, 'gross' => $gross, 'available' => $available],
        fn (string $code): CartCoupon => new CartCoupon(hash: $code, code: $code, type: 'fixed', value: 1.0));
    expect(fn () => $validation->validateForCheckout())->toThrow(ValidationException::class)
        ->and($cart->total())->toBe(24.4);
})->with([
    [20.0, 4.4, 24.4, 5],
    [10.0, 2.2, 12.2, 1],
]);

it('revalidates expiry minimum definition and server eligibility of attached coupons', function (string $case): void {
    $this->actingAs(new GenericUser(['id' => 7]));
    $cart = resolveCart();
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $coupon = new CartCoupon(hash: 'v1', code: 'MEMBER', type: 'fixed', value: 1.0, expiresAt: now()->addMinute());
    $cart->addCoupon($coupon);
    if ($case === 'expiry') {
        $this->travel(2)->minutes();
    }
    $validation = new CheckoutValidation($cart,
        fn (): array => ['net' => 10.0, 'vat' => 0.0, 'gross' => 10.0, 'available' => 5],
        function () use ($coupon, $case): CartCoupon {
            if ($case === 'eligibility') {
                throw ValidationException::withMessages(['coupon' => 'Not eligible.']);
            }

            return new CartCoupon(hash: $coupon->hash, code: $coupon->code, type: $coupon->type,
                value: $case === 'definition' ? 2.0 : $coupon->value,
                expiresAt: $coupon->expiresAt, minCartAmount: $case === 'minimum' ? 100.0 : null);
        });
    expect(fn () => $validation->validateForCheckout())->toThrow(ValidationException::class)
        ->and($cart->hasCartCoupon('MEMBER'))->toBeTrue();
})->with(['expiry', 'minimum', 'definition', 'eligibility']);

it('requires authenticated identity valid coupon selectors and nonempty checkout carts', function (): void {
    $validation = new CheckoutValidation(resolveCart(),
        fn (): array => ['net' => 10.0, 'vat' => 0.0, 'gross' => 10.0, 'available' => 5],
        fn (string $code): CartCoupon => new CartCoupon(hash: $code, code: $code, type: 'fixed', value: 1.0));
    expect(fn () => $validation->applyCoupon('MEMBER'))->toThrow(AuthorizationException::class);
    $this->actingAs(new GenericUser(['id' => 7]));
    expect(fn () => $validation->applyCoupon('bad code'))->toThrow(ValidationException::class);
    expect(fn () => $validation->validateForCheckout())->toThrow(ValidationException::class);
});

it('rejects adjustment quantities in the positive-product checkout recipe', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $this->actingAs(new GenericUser(['id' => 7]));
    $cart = resolveCart();
    $cart->add(id: 'adjustment', name: 'Legacy adjustment', qty: -1, price: 10.0);
    $validation = new CheckoutValidation($cart,
        fn (): array => ['net' => 10.0, 'vat' => 0.0, 'gross' => 10.0, 'available' => 5],
        fn (string $code): CartCoupon => new CartCoupon(hash: $code, code: $code, type: 'fixed', value: 1.0));
    expect(fn () => $validation->validateForCheckout())->toThrow(ValidationException::class)
        ->and($cart->content()->first()?->qty)->toBe(-1);
})->with(['session', 'database']);

it('accepts the package numeric-string precision setting at checkout', function (): void {
    config(['cart.format.decimals' => '2']);
    $this->actingAs(new GenericUser(['id' => 7]));
    $cart = resolveCart();
    $cart->add(id: 1, name: 'Product', qty: 1, price: 10.0);
    $validation = new CheckoutValidation($cart,
        fn (): array => ['net' => 10.0, 'vat' => 0.0, 'gross' => 10.0, 'available' => 5],
        fn (string $code): CartCoupon => new CartCoupon(hash: $code, code: $code, type: 'fixed', value: 1.0));
    $validation->validateForCheckout();
    expect($cart->total())->toBe(10.0);
});
