<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use OfflineAgency\LaravelCart\Contracts\Couponable;
use OfflineAgency\LaravelCart\Traits\HasDiscount;

/**
 * Eloquent model backed by the standard coupon columns, used to exercise the
 * HasDiscount trait without touching the database.
 */
class PromoModel extends Model implements Couponable
{
    use HasDiscount;

    protected $table = 'promos';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;
}
