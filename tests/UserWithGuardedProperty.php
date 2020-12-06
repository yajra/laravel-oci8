<?php

namespace Yajra\Oci8\Tests;

class UserWithGuardedProperty extends User
{
    protected $table = 'users';

    protected $guarded = ['id'];
}
