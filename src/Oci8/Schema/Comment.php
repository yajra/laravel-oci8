<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Connection;

class Comment
{
    /**
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Set table and column comments.
     *
     * @param  \Yajra\Oci8\Schema\OracleBlueprint $blueprint
     */
    public function setComments(OracleBlueprint $blueprint)
    {
        // Comment set by $table->comment('comment');
        if ($blueprint->commentTable != null)
        {
            $this->connection->statement(sprintf('comment on table %s is \'%s\'', $blueprint->getTable(), $blueprint->commentTable));
        }

        // Comments set by $table->string('column')->comment('comment');
        foreach ($blueprint->getColumns() as $column)
        {
            if (isset($column['comment']))
            {
                $this->connection->statement(sprintf('comment on column %s.%s is \'%s\'', $blueprint->getTable(), $column['name'], $column['comment']));
            }
        }

        // Comments set by $table->commentColumn('column', 'comment');
        foreach ($blueprint->commentColumn as $column)
        {
            if (isset($column['comment']))
            {
                $this->connection->statement(sprintf('comment on column %s.%s is \'%s\'', $blueprint->getTable(), $column['name'], $column['comment']));
            }
        }
    }
}
