<?php

namespace Yajra\Oci8;

use Yajra\Oci8\Validation\Oci8DatabasePresenceVerifier;
use Illuminate\Validation\ValidationServiceProvider;

class Oci8ValidationServiceProvider extends ValidationServiceProvider
{
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function ($app) {
            return new Oci8DatabasePresenceVerifier($app['db']);
        });
    }
}
