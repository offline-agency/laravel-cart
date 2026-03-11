<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Traits;

use Carbon\Carbon;
use OfflineAgency\LaravelCart\Enums\CouponType;

/**
 * Eloquent model trait that provides a default implementation of the Couponable
 * interface by reading from standard database columns:
 *   coupon_code, coupon_type, coupon_value, coupon_expires_at,
 *   coupon_usage_limit, coupon_min_cart_amount.
 *
 * Usage:
 *   class Promo extends Model implements Couponable
 *   {
 *       use HasDiscount;
 *   }
 */
trait HasDiscount
{
    /**
     * Get the unique coupon hash/identifier (defaults to the model's primary key).
     */
    public function getHash(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Get the human-readable coupon code from the `coupon_code` column.
     */
    public function getCode(): string
    {
        return (string) ($this->coupon_code ?? '');
    }

    /**
     * Get the coupon type from the `coupon_type` column.
     */
    public function getCouponType(): CouponType
    {
        return CouponType::from((string) ($this->coupon_type ?? 'fixed'));
    }

    /**
     * Get the coupon value from the `coupon_value` column.
     */
    public function getCouponValue(): float
    {
        return (float) ($this->coupon_value ?? 0.0);
    }

    /**
     * Get the expiry date from the `coupon_expires_at` column.
     */
    public function getCouponExpiresAt(): ?Carbon
    {
        $value = $this->coupon_expires_at ?? null;

        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    /**
     * Get the usage limit from the `coupon_usage_limit` column.
     */
    public function getCouponUsageLimit(): ?int
    {
        $value = $this->coupon_usage_limit ?? null;

        return $value !== null ? (int) $value : null;
    }

    /**
     * Get the minimum cart amount from the `coupon_min_cart_amount` column.
     */
    public function getMinCartAmount(): ?float
    {
        $value = $this->coupon_min_cart_amount ?? null;

        return $value !== null ? (float) $value : null;
    }

    /**
     * Determine whether the coupon has expired.
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getCouponExpiresAt();

        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * Determine whether the coupon is applicable to the given cart total.
     */
    public function isApplicableTo(float $cartTotal): bool
    {
        $min = $this->getMinCartAmount();

        if ($min === null) {
            return true;
        }

        return $cartTotal >= $min;
    }

    /**
     * Return the coupon data as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        /** @var array<string, mixed> $baseArray */
        $baseArray = method_exists(get_parent_class($this) ?: '', 'toArray')
            ? parent::toArray()
            : [];

        return array_merge($baseArray, [
            'hash' => $this->getHash(),
            'code' => $this->getCode(),
            'type' => $this->getCouponType()->value,
            'value' => $this->getCouponValue(),
            'expiresAt' => $this->getCouponExpiresAt()?->toIso8601String(),
            'usageLimit' => $this->getCouponUsageLimit(),
            'minCartAmount' => $this->getMinCartAmount(),
        ]);
    }
}
