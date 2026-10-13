<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\Tests\Fixtures\BareBuyable;

function covBareBuyable(): BareBuyable
{
    return new BareBuyable;
}

it('falls back to the id property when getKey is not available', function () {
    expect(covBareBuyable()->getId())->toBe(7);
});

it('returns defaults when no attributes are set', function () {
    $item = covBareBuyable();

    expect($item->getName())->toBe('')
        ->and($item->getSubtitle())->toBe('')
        ->and($item->getQty())->toBe(1)
        ->and($item->getPrice())->toBe(0.0)
        ->and($item->getVat())->toBe(0.0)
        ->and($item->getTotalPrice())->toBe(0.0)
        ->and($item->getUrlImg())->toBe('')
        ->and($item->getWeight())->toBe(0.0)
        ->and($item->getOptions())->toBe([])
        ->and($item->getBuyableDescription())->toBe('')
        ->and($item->getVatFcCode())->toBe('')
        ->and($item->getProductFcCode())->toBe('');
});

it('reads values from fallback columns', function () {
    $item = covBareBuyable();
    $item->title = 'Title';
    $item->description = 'Description';
    $item->image = 'image.png';
    $item->weight = '2.5';
    $item->vatFcCode = 'V1';
    $item->productFcCode = 'P1';

    expect($item->getName())->toBe('Title')
        ->and($item->getSubtitle())->toBe('Description')
        ->and($item->getUrlImg())->toBe('image.png')
        ->and($item->getWeight())->toBe(2.5)
        ->and($item->getBuyableDescription())->toBe('Title')
        ->and($item->getVatFcCode())->toBe('V1')
        ->and($item->getProductFcCode())->toBe('P1');
});

it('prefers primary columns over fallbacks', function () {
    $item = covBareBuyable();
    $item->url_img = 'primary.png';
    $item->image = 'fallback.png';
    $item->vat_fc_code = 'VAT';
    $item->product_fc_code = 'PROD';

    expect($item->getUrlImg())->toBe('primary.png')
        ->and($item->getVatFcCode())->toBe('VAT')
        ->and($item->getProductFcCode())->toBe('PROD');
});

it('uses the description for the legacy buyable description', function () {
    $item = covBareBuyable();
    $item->description = 'Only description';

    expect($item->getBuyableDescription())->toBe('Only description');
});

it('stores values through setters', function () {
    $item = covBareBuyable();
    $item->setId(99);
    $item->setName('Name');
    $item->setSubtitle('Sub');
    $item->setQty(3);
    $item->setPrice(10.0);
    $item->setTotalPrice(12.2);
    $item->setVat(2.2);
    $item->setUrlImg('img.png');

    expect($item->getId())->toBe(99)
        ->and($item->getBuyableIdentifier())->toBe(99)
        ->and($item->getName())->toBe('Name')
        ->and($item->getSubtitle())->toBe('Sub')
        ->and($item->getQty())->toBe(3)
        ->and($item->getPrice())->toBe(10.0)
        ->and($item->getBuyablePrice())->toBe(10.0)
        ->and($item->getVat())->toBe(2.2)
        ->and($item->getTotalPrice())->toBe(12.2)
        ->and($item->totalPrice)->toBe(12.2)
        ->and($item->getUrlImg())->toBe('img.png');
});

it('returns options only when cartOptions is an array', function () {
    $item = covBareBuyable();
    $item->cartOptions = 'not-an-array';

    expect($item->getOptions())->toBe([]);

    $item->setOptions(['size' => 'L']);

    expect($item->getOptions())->toBe(['size' => 'L']);
});
