<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

class OracleBlueprint extends Blueprint
{
    /**
     * Table comment.
     *
     * @var string|null
     */
    public ?string $comment = null;

    /**
     * Column comments.
     *
     * @var array
     */
    public array $commentColumns = [];

    /**
     * Database prefix variable.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Set table prefix settings.
     *
     * @param  string  $prefix
     */
    public function setTablePrefix(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

    /**
     * Create a default index name for the table.
     *
     * @param  string  $type
     * @param  array  $columns
     * @return string
     */
    protected function createIndexName($type, array $columns): string
    {
        $short_type = [
            'primary' => 'pk',
            'foreign' => 'fk',
            'unique'  => 'uk',
        ];

        $type = $short_type[$type] ?? $type;

        $index = strtolower($this->prefix . $this->table . '_' . implode('_', $columns) . '_' . $type);

        $index = str_replace(['-', '.'], '_', $index);

        //shorten the name if it is longer than 30 chars
        while (strlen($index) > 30) {
            $parts = explode('_', $index);

            for ($i = 0; $i < count($parts); $i++) {
                //if any part is longer than 2 chars, take one off
                $len = strlen($parts[$i]);
                if ($len > 2) {
                    $parts[$i] = substr($parts[$i], 0, $len - 1);
                }
            }

            $index = implode('_', $parts);
        }

        return $index;
    }

    /**
     * Create a new nvarchar2 column on the table.
     *
     * @param  string  $column
     * @param  int  $length
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function nvarchar2(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('nvarchar2', $column, compact('length'));
    }
}
