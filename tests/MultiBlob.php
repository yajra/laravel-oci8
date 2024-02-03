<?php

namespace Yajra\Oci8\Tests;

use Yajra\Oci8\Eloquent\OracleEloquent;

/**
 * @property int id
 * @property array blob_1
 * @property array blob_2
 * @property int status
 */
class MultiBlob extends OracleEloquent
{
    protected $guarded = [];

    protected $binaries = ['blob_1', 'blob_2'];

    protected $casts = [
        'blob_1' => 'array',
        'blob_2' => 'array',
    ];
}
