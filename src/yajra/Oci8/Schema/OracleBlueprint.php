<?php

namespace yajra\Oci8\Schema;

use Illuminate\Database\Schema\Blueprint;

class OracleBlueprint extends Blueprint
{

    /**
     * Database prefix variable
     *
     * @var string
     */
    protected $prefix;

    /**
     * set table prefix settings
     *
     * @param string $prefix
     */
    public function setTablePrefix($prefix = '')
    {
        $this->prefix = $prefix;
    }

    /**
     * Create a default index name for the table.
     *
     * @param  string $type
     * @param  array $columns
     * @return string
     */
    protected function createIndexName($type, array $columns)
    {
        $index = strtolower($this->prefix . $this->table . '_' . implode('_', $columns) . '_' . $type);

        // max index name length is 30 chars
        return substr(str_replace(['-', '.'], '_', $index), 0, 30);
    }

}
