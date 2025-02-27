<?php

namespace Yajra\Oci8\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class OracleUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        if (empty($credentials)) {
            return null;
        }

        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // Eloquent User "model" that will be utilized by the Guard instances.
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if (! Str::contains($key, 'password')) {
                $query->whereRaw("upper({$key}) = upper(?)", [$value]);
            }
        }

        return $query->first();
    }
}
