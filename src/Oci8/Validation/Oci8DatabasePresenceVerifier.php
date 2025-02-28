<?php

namespace Yajra\Oci8\Validation;

use Illuminate\Validation\DatabasePresenceVerifier;
use Yajra\Oci8\Oci8Connection;

class Oci8DatabasePresenceVerifier extends DatabasePresenceVerifier
{
    /**
     * Count the number of objects in a collection having the given value.
     *
     * @param  string  $collection
     * @param  string  $column
     * @param  string  $value
     * @param  int|null  $excludeId
     * @param  string|null  $idColumn
     */
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
    {
        $connection = $this->table($collection)->getConnection();

        if (! $connection instanceof Oci8Connection) {
            return parent::getCount($collection, $column, $value, $excludeId, $idColumn, $extra);
        }

        $connection->useCaseInsensitiveSession();
        $count = parent::getCount($collection, $column, $value, $excludeId, $idColumn, $extra);
        $connection->useCaseSensitiveSession();

        return $count;
    }

    /**
     * Count the number of objects in a collection with the given values.
     *
     * @param  string  $collection
     * @param  string  $column
     */
    public function getMultiCount($collection, $column, array $values, array $extra = []): int
    {
        $connection = $this->table($collection)->getConnection();

        if (! $connection instanceof Oci8Connection) {
            return parent::getMultiCount($collection, $column, $values, $extra);
        }

        $connection->useCaseInsensitiveSession();
        $count = parent::getMultiCount($collection, $column, $values, $extra);
        $connection->useCaseSensitiveSession();

        return $count;
    }
}
