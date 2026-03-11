<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Contracts;

use Carbon\Carbon;
use OfflineAgency\LaravelCart\Enums\CouponType;

interface Couponable
{
    /**
     * Get the unique coupon hash/identifier.
     */
    public function getHash(): string;

    /**
     * Get the human-readable coupon code.
     */
    public function getCode(): string;

    /**
     * Get the coupon type as a CouponType enum.
     */
    public function getCouponType(): CouponType;

    /**
     * Get the coupon value (amount or percentage).
     */
    public function getCouponValue(): float;

    /**
     * Get the expiry date, or null if the coupon does not expire.
     */
    public function getCouponExpiresAt(): ?Carbon;

    /**
     * Get the maximum number of times this coupon can be used, or null for unlimited.
     */
    public function getCouponUsageLimit(): ?int;

    /**
     * Get the minimum cart amount required to apply this coupon, or null for no minimum.
     */
    public function getMinCartAmount(): ?float;

    /**
     * Determine whether the coupon has expired.
     */
    public function isExpired(): bool;

    /**
     * Determine whether the coupon is applicable to the given cart total.
     */
    public function isApplicableTo(float $cartTotal): bool;

    /**
     * Return the coupon as an array (for serialisation).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
