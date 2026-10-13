<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures\Codec;

use Illuminate\Database\Eloquent\Model;

/**
 * An Eloquent model associated with a cart item; decoding must restore it.
 */
final class CodecModel extends Model
{
    protected $guarded = [];
}
