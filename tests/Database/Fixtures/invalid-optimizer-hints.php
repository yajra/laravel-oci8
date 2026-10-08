<?php

namespace Yajra\Oci8\Tests\Database\Fixtures;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Yajra\Oci8\Eloquent\OracleEloquent;

/**
 * @param  EloquentBuilder<OracleEloquent>  $eloquent
 */
function invalidOptimizerHints(Builder $query, EloquentBuilder $eloquent): void
{
    $query->hint(['ALL_ROWS']);
    DB::table('users')->hint('ALL_ROWS', ['FIRST_ROWS']);
    $eloquent->hint(['ALL_ROWS']);
    OracleEloquent::hint(['ALL_ROWS']);
}
