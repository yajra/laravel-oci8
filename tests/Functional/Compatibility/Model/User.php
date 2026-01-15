<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property string name
 * @property string email
 */
class User extends Model
{
    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(Child::class);
    }
}
