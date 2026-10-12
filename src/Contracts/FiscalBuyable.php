<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Contracts;

interface FiscalBuyable extends Buyable
{
    public function getVatFcCode(): string;

    public function getProductFcCode(): string;
}
