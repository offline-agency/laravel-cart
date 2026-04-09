<?php

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use OfflineAgency\LaravelCart\CanBeBought;
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;
use OfflineAgency\LaravelCart\Traits\HasFiscalCodes;

class ProductWithTrait implements FiscalBuyable
{
    use CanBeBought, HasFiscalCodes;

    public $id;

    public $name;

    public $title;

    public $description;

    public $price;

    public $subtitle;

    public $qty;

    public $totalPrice;

    public $vat;

    public $vatFcCode;

    public $productFcCode;

    public $urlImg;

    public $cartOptions = [];

    public function __construct($id = null, $name = null, $title = null, $description = null, $price = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->title = $title;
        $this->description = $description;
        $this->price = $price;
    }

    public function getKey()
    {
        return $this->id;
    }
}
