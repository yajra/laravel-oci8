<?php

namespace Yajra\Oci8;

use Illuminate\Validation\ValidationServiceProvider;
use Yajra\Oci8\Validation\Oci8DatabasePresenceVerifier;

class Oci8ValidationServiceProvider extends ValidationServiceProvider
{
    protected function registerPresenceVerifier(): void
    {
        $this->app->singleton('validation.presence', fn ($app) => new Oci8DatabasePresenceVerifier($app['db']));
    }
}
