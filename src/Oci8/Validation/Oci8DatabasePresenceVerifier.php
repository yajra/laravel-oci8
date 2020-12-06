<?php

namespace Yajra\Oci8\Validation;

use Yajra\Oci8\Oci8Connection;
use Illuminate\Validation\DatabasePresenceVerifier;

class Oci8DatabasePresenceVerifier extends DatabasePresenceVerifier
{
    /**
     * Count the number of objects in a collection having the given value.
     *
     * @param string $collection
     * @param string $column
     * @param string $value
     * @param int|null $excludeId
     * @param string|null $idColumn
     * @param array $extra
     * @return int
     */
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        $connection = $this->table($collection)->getConnection();

        if (! $connection instanceof Oci8Connection) {
            return parent::getCount($collection, $column, $value, $excludeId, $idColumn, $extra);
        }

        $connection->setSessionVars(['NLS_COMP' => 'LINGUISTIC', 'NLS_SORT' => 'BINARY_CI']);
        $count = parent::getCount($collection, $column, $value, $excludeId, $idColumn, $extra);
        $connection->setSessionVars(['NLS_COMP' => 'BINARY', 'NLS_SORT' => 'BINARY']);

        return $count;
    }

    /**
     * Count the number of objects in a collection with the given values.
     *
     * @param string $collection
     * @param string $column
     * @param array $values
     * @param array $extra
     * @return int
     */
    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        $connection = $this->table($collection)->getConnection();

        if (! $connection instanceof Oci8Connection) {
            return parent::getMultiCount($collection, $column, $values, $extra);
        }

        $connection->setSessionVars(['NLS_COMP' => 'LINGUISTIC', 'NLS_SORT' => 'BINARY_CI']);
        $count = parent::getMultiCount($collection, $column, $values, $extra);
        $connection->setSessionVars(['NLS_COMP' => 'BINARY', 'NLS_SORT' => 'BINARY']);

        return $count;
    }
}
