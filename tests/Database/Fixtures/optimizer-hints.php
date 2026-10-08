<?php

namespace Yajra\Oci8\Tests\Database\Fixtures;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Yajra\Oci8\Eloquent\OracleEloquent;
use Yajra\Oci8\Query\OracleBuilder;

use function PHPStan\Testing\assertType;

/**
 * @param  EloquentBuilder<OracleEloquent>  $eloquent
 */
function optimizerHints(Builder $query, EloquentBuilder $eloquent, OracleBuilder $oracle): void
{
    assertType('Illuminate\Database\Query\Builder', DB::table('users')->hint('ALL_ROWS'));
    assertType('Illuminate\Database\Query\Builder', DB::query()->hint('ALL_ROWS', 'LEADING(users)')->limit(10));
    assertType('Illuminate\Database\Query\Builder', DB::connection('oracle')->table('users')->hint('FIRST_ROWS'));
    assertType('Illuminate\Database\Query\Builder', $query->hint()->hint('ALL_ROWS')->useIndex('users_email_index'));
    assertType('Illuminate\Support\Collection<int, stdClass>', $query->hint('ALL_ROWS')->get());
    assertType('Yajra\Oci8\Query\OracleBuilder', $oracle->hint('ALL_ROWS'));
    assertType('Illuminate\Database\Eloquent\Builder<Yajra\Oci8\Eloquent\OracleEloquent>', $eloquent->hint('ALL_ROWS'));
    assertType('Illuminate\Database\Eloquent\Builder<Yajra\Oci8\Eloquent\OracleEloquent>', OracleEloquent::query()->hint('ALL_ROWS')->limit(10));
    assertType('Illuminate\Database\Eloquent\Builder<Yajra\Oci8\Eloquent\OracleEloquent>', OracleEloquent::hint('ALL_ROWS', 'LEADING(users)'));
    assertType('Yajra\Oci8\Eloquent\OracleEloquent|null', $eloquent->hint('ALL_ROWS')->first());
    assertType('Illuminate\Database\Eloquent\Collection<int, Yajra\Oci8\Eloquent\OracleEloquent>', $eloquent->hint('ALL_ROWS')->get());
}
