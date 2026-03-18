<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel\Fixtures\Models\Money;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Yajra\Oci8\Tests\Functional\Compatibility\Laravel\Fixtures\Factories\Money\PriceFactory;

class Price extends Model
{
    /** @use HasFactory<PriceFactory> */
    use HasFactory;

    protected $table = 'prices';

    protected static string $factory = PriceFactory::class;
}
