<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use Carbon\Carbon;
use JsonSerializable;
use OfflineAgency\LaravelCart\Contracts\Couponable;
use OfflineAgency\LaravelCart\Enums\CouponType;

final readonly class CartCoupon implements Couponable, JsonSerializable
{
    public function __construct(
        public string $hash,
        public string $code,
        public string $type,
        public float $value,
        public bool $isGlobal = false,
        public ?Carbon $expiresAt = null,
        public ?int $usageLimit = null,
        public ?float $minCartAmount = null,
    ) {}

    public function isPercentage(): bool
    {
        return $this->type === 'percentage';
    }

    public function isFixed(): bool
    {
        return $this->type === 'fixed';
    }

    /**
     * Get the coupon type as a CouponType enum.
     */
    public function couponType(): CouponType
    {
        return CouponType::from($this->type);
    }

    /**
     * {@inheritDoc}
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * {@inheritDoc}
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * {@inheritDoc}
     */
    public function getCouponType(): CouponType
    {
        return $this->couponType();
    }

    /**
     * {@inheritDoc}
     */
    public function getCouponValue(): float
    {
        return $this->value;
    }

    /**
     * {@inheritDoc}
     */
    public function getCouponExpiresAt(): ?Carbon
    {
        return $this->expiresAt;
    }

    /**
     * {@inheritDoc}
     */
    public function getCouponUsageLimit(): ?int
    {
        return $this->usageLimit;
    }

    /**
     * {@inheritDoc}
     */
    public function getMinCartAmount(): ?float
    {
        return $this->minCartAmount;
    }

    /**
     * Determine whether the coupon has expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isPast();
    }

    /**
     * Determine whether the coupon is applicable to the given cart total.
     * Returns false when the cart total is below the minimum required amount.
     */
    public function isApplicableTo(float $cartTotal): bool
    {
        if ($this->minCartAmount === null) {
            return true;
        }

        return $cartTotal >= $this->minCartAmount;
    }

    /**
     * Return the coupon as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'isGlobal' => $this->isGlobal,
            'expiresAt' => $this->expiresAt?->toIso8601String(),
            'usageLimit' => $this->usageLimit,
            'minCartAmount' => $this->minCartAmount,
        ];
    }

    /**
     * Implement JsonSerializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
