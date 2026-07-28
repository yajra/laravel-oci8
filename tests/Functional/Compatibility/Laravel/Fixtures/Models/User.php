<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel\Fixtures\Models;

use Illuminate\Foundation\Auth\User as FoundationUser;

class User extends FoundationUser
{
    protected $primaryKey = 'internal_id';
}
