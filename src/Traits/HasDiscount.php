<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Traits;

use Carbon\Carbon;
use DateTimeInterface;
use OfflineAgency\LaravelCart\Enums\CouponType;
use OfflineAgency\LaravelCart\Support\Coerce;
use ReflectionMethod;

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
        return Coerce::string($this->getKey() ?? '');
    }

    /**
     * Get the human-readable coupon code from the `coupon_code` column.
     */
    public function getCode(): string
    {
        return Coerce::string($this->coupon_code ?? '');
    }

    /**
     * Get the coupon type from the `coupon_type` column.
     */
    public function getCouponType(): CouponType
    {
        return CouponType::from(Coerce::string($this->coupon_type ?? 'fixed'));
    }

    /**
     * Get the coupon value from the `coupon_value` column.
     */
    public function getCouponValue(): float
    {
        return Coerce::float($this->coupon_value ?? 0.0);
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

        if ($value instanceof Carbon) {
            return $value;
        }

        return $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse(Coerce::string($value));
    }

    /**
     * Get the usage limit from the `coupon_usage_limit` column.
     */
    public function getCouponUsageLimit(): ?int
    {
        $value = $this->coupon_usage_limit ?? null;

        return $value !== null ? Coerce::int($value) : null;
    }

    /**
     * Get the minimum cart amount from the `coupon_min_cart_amount` column.
     */
    public function getMinCartAmount(): ?float
    {
        $value = $this->coupon_min_cart_amount ?? null;

        return $value !== null ? Coerce::float($value) : null;
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
     * Return the coupon data as an array, merged onto the parent class's
     * toArray() (e.g. the Eloquent attributes) when there is one.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->parentToArray(), [
            'hash' => $this->getHash(),
            'code' => $this->getCode(),
            'type' => $this->getCouponType()->value,
            'value' => $this->getCouponValue(),
            'expiresAt' => $this->getCouponExpiresAt()?->toIso8601String(),
            'usageLimit' => $this->getCouponUsageLimit(),
            'minCartAmount' => $this->getMinCartAmount(),
        ]);
    }

    /**
     * The parent class's toArray(), which this trait's toArray() overrides.
     * Called through reflection because the trait is also used by classes
     * without a parent.
     *
     * @return array<array-key, mixed>
     */
    private function parentToArray(): array
    {
        $parent = get_parent_class($this);

        if ($parent === false || ! method_exists($parent, 'toArray')) {
            return [];
        }

        $array = (new ReflectionMethod($parent, 'toArray'))->invoke($this);

        return is_array($array) ? $array : [];
    }
}
