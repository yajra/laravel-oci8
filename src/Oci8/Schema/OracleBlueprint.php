<?php

namespace Yajra\Oci8\Schema;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Yajra\Oci8\Oci8Connection;

/**
 * @property Oci8Connection $connection
 */
class OracleBlueprint extends Blueprint
{
    /**
     * Table comment.
     */
    public ?string $comment = null;

    /**
     * Column comments.
     */
    public array $commentColumns = [];

    public function __construct(Connection $connection, $table, ?Closure $callback = null)
    {
        parent::__construct($connection, $table, $callback);
    }

    /**
     * Set database object max length name settings.
     */
    public function setMaxLength(int $maxLength = 30): void
    {
        $this->connection->setMaxLength($maxLength);
    }

    /**
     * Create a default index name for the table.
     *
     * @param  string  $type
     */
    protected function createIndexName($type, array $columns): string
    {
        // if we are creating a compound/composite index with more than 2 columns, do not use the standard naming scheme
        if (count($columns) <= 2) {
            $short_type = [
                'primary' => 'pk',
                'foreign' => 'fk',
                'unique' => 'uk',
            ];

            $type = $short_type[$type] ?? $type;

            $index = strtolower($this->connection->getTablePrefix().$this->table.'_'.implode('_', $columns).'_'.$type);

            $index = str_replace(['-', '.', ' '], '_', $index);
            while (strlen($index) > $this->connection->getMaxLength()) {
                $parts = explode('_', $index);

                for ($i = 0; $i < count($parts); $i++) {
                    // if any part is longer than 2 chars, take one off
                    $len = strlen($parts[$i]);
                    if ($len > 2) {
                        $parts[$i] = mb_substr($parts[$i], 0, $len - 1);
                    }
                }

                $index = implode('_', $parts);
            }
        } else {
            $index = mb_substr($this->table, 0, 10).'_comp_'.str_replace('.', '_', microtime(true));
        }

        return $index;
    }

    /**
     * Create a new nvarchar2 column on the table.
     */
    public function nvarchar2(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('nvarchar2', $column, compact('length'));
    }

    /**
     * Create a new float column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     */
    public function float($column, $precision = 126): ColumnDefinition
    {
        return parent::float($column, $precision);
    }
}
