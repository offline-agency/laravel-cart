<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Traits;

trait HasFiscalCodes
{
    public function getVatFcCode(): string
    {
        return (string) ($this->vat_fc_code ?? $this->vatFcCode ?? '');
    }

    public function getProductFcCode(): string
    {
        return (string) ($this->product_fc_code ?? $this->productFcCode ?? '');
    }
}
