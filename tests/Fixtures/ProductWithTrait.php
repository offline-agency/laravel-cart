<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use OfflineAgency\LaravelCart\CanBeBought;
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;
use OfflineAgency\LaravelCart\Traits\HasFiscalCodes;

/**
 * FiscalBuyable implemented through the CanBeBought and HasFiscalCodes traits,
 * with a getKey() method like an Eloquent model.
 */
class ProductWithTrait implements FiscalBuyable
{
    use CanBeBought, HasFiscalCodes;

    public int|string|null $id;

    public ?string $name;

    public ?string $title;

    public ?string $description;

    public ?float $price;

    public ?string $subtitle = null;

    public ?int $qty = null;

    public ?float $totalPrice = null;

    public ?float $vat = null;

    public ?string $vatFcCode = null;

    public ?string $productFcCode = null;

    public ?string $urlImg = null;

    /** @var array<array-key, mixed> */
    public array $cartOptions = [];

    public function __construct(int|string|null $id = null, ?string $name = null, ?string $title = null, ?string $description = null, ?float $price = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->title = $title;
        $this->description = $description;
        $this->price = $price;
    }

    public function getKey(): int|string|null
    {
        return $this->id;
    }
}
