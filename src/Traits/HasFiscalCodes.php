<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Traits;

use OfflineAgency\LaravelCart\Support\Coerce;

trait HasFiscalCodes
{
    public function getVatFcCode(): string
    {
        return Coerce::string($this->fiscalCodeAttribute('vat_fc_code', 'vatFcCode') ?? '');
    }

    public function getProductFcCode(): string
    {
        return Coerce::string($this->fiscalCodeAttribute('product_fc_code', 'productFcCode') ?? '');
    }

    /**
     * The first non-null of the given optional attributes: model attributes or
     * public properties, which plain classes do not have to declare.
     */
    private function fiscalCodeAttribute(string ...$names): mixed
    {
        foreach ($names as $name) {
            $value = $this->{$name} ?? null;

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
