<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property string name
 * @property int user_id
 */
class Child extends Model
{
    protected $guarded = [];
}
