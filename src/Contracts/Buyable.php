<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Contracts;

interface Buyable
{
    /**
     * @return int|string
     */
    public function getId();

    public function getName(): string;

    public function getSubtitle(): string;

    public function getQty(): int;

    public function getPrice(): float;

    public function getTotalPrice(): float;

    public function getVat(): float;

    public function getUrlImg(): string;

    /**
     * @return array<array-key, mixed>
     */
    public function getOptions(): array;

    public function getWeight(): float;
}
