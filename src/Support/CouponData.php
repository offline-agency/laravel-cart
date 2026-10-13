<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Support;

use Carbon\Carbon;
use OfflineAgency\LaravelCart\CartCoupon;
use UnexpectedValueException;

/**
 * Rebuilds cart-level coupons from their stored array form (CartCoupon::toArray()).
 *
 * @internal
 */
final class CouponData
{
    /**
     * Decode a JSON list of stored coupons, keyed by coupon hash. An empty or
     * non-list payload yields no coupons.
     *
     * @return array<string, CartCoupon>
     */
    public static function decodeList(?string $json, bool $isGlobalDefault): array
    {
        $data = $json !== null && $json !== '' ? json_decode($json, true) : null;

        if (! is_array($data)) {
            return [];
        }

        $coupons = [];

        foreach ($data as $item) {
            $coupon = self::toCoupon($item, $isGlobalDefault);
            $coupons[$coupon->hash] = $coupon;
        }

        return $coupons;
    }

    public static function toCoupon(mixed $data, bool $isGlobalDefault): CartCoupon
    {
        if (! is_array($data)) {
            throw new UnexpectedValueException(sprintf('Expected a stored coupon array, %s given.', get_debug_type($data)));
        }

        return new CartCoupon(
            hash: Coerce::string($data['hash'] ?? null),
            code: Coerce::string($data['code'] ?? null),
            type: Coerce::string($data['type'] ?? null),
            value: Coerce::float($data['value'] ?? null),
            isGlobal: (bool) ($data['isGlobal'] ?? $isGlobalDefault),
            expiresAt: isset($data['expiresAt']) ? Carbon::parse(Coerce::string($data['expiresAt'])) : null,
            usageLimit: isset($data['usageLimit']) ? Coerce::int($data['usageLimit']) : null,
            minCartAmount: isset($data['minCartAmount']) ? Coerce::float($data['minCartAmount']) : null,
        );
    }
}
