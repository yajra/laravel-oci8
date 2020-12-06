<?php

namespace Yajra\Oci8;

use Illuminate\Validation\ValidationServiceProvider;
use Yajra\Oci8\Validation\Oci8DatabasePresenceVerifier;

class Oci8ValidationServiceProvider extends ValidationServiceProvider
{
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function ($app) {
            return new Oci8DatabasePresenceVerifier($app['db']);
        });
    }
}
