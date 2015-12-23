<?php

namespace Yajra\Oci8\Schema;

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
     * Table comment
     *
     * @var string
     */
    public $comment = null;

    /**
     * Column comments
     *
     * @var array
     */
    public $commentColumns = [];

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
     * Add creation and update timestampTz columns to the table.
     *
     * @return void
     */
    public function timestampsTz()
    {
        $this->timestampTz('created_at');

        $this->timestampTz('updated_at');
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
