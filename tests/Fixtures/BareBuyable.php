<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use OfflineAgency\LaravelCart\CanBeBought;
use OfflineAgency\LaravelCart\Traits\HasFiscalCodes;

/**
 * Plain (non-Eloquent) consumer of CanBeBought and HasFiscalCodes without a
 * getKey() method. It declares every attribute the traits read, untyped like
 * the loose attributes of a model, so each fallback can be exercised.
 */
class BareBuyable
{
    use CanBeBought, HasFiscalCodes;

    public mixed $id = 7;

    public mixed $name = null;

    public mixed $title = null;

    public mixed $subtitle = null;

    public mixed $description = null;

    public mixed $qty = null;

    public mixed $price = null;

    public mixed $vat = null;

    public mixed $totalPrice = null;

    public mixed $url_img = null;

    public mixed $urlImg = null;

    public mixed $image = null;

    public mixed $weight = null;

    public mixed $cartOptions = null;

    public mixed $vat_fc_code = null;

    public mixed $vatFcCode = null;

    public mixed $product_fc_code = null;

    public mixed $productFcCode = null;
}
