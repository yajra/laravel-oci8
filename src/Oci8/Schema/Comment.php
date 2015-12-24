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
        // Comment set by $table->comment = 'comment';
        $this->commentTable($blueprint);

        // Comments set by $table->string('column')->comment('comment');
        $this->fluentComments($blueprint);

        // Comments set by $table->commentColumns = ['column' => 'comment'];
        $this->commentColumns($blueprint);
    }

    /**
     * Run the comment on table statement.
     *
     * @param \Yajra\Oci8\Schema\OracleBlueprint $blueprint
     */
    private function commentTable(OracleBlueprint $blueprint)
    {
        if ($blueprint->comment != null) {
            $this->connection->statement(sprintf(
                'comment on table %s is \'%s\'', $blueprint->getTable(),
                $blueprint->comment
            ));
        }
    }

    /**
     * Add comments set via fluent setter.
     *
     * @param \Yajra\Oci8\Schema\OracleBlueprint $blueprint
     */
    private function fluentComments(OracleBlueprint $blueprint)
    {
        foreach ($blueprint->getColumns() as $column) {
            if (isset($column['comment'])) {
                $this->commentColumn($blueprint->getTable(), $column['name'], $column['comment']);
            }
        }
    }

    /**
     * Run the comment on column statement
     *
     * @param  string $table
     * @param  string $column
     * @param  string $comment
     */
    private function commentColumn($table, $column, $comment)
    {
        $this->connection->statement(sprintf('comment on column %s.%s is \'%s\'', $table, $column, $comment));
    }

    /**
     * Add comments on columns.
     *
     * @param \Yajra\Oci8\Schema\OracleBlueprint $blueprint
     */
    private function commentColumns(OracleBlueprint $blueprint)
    {
        foreach ($blueprint->commentColumns as $column => $comment) {
            $this->commentColumn($blueprint->getTable(), $column, $comment);
        }
    }
}
