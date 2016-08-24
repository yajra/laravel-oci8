<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;
use Yajra\Oci8\OracleReservedWords;

class Comment extends Grammar
{
    use OracleReservedWords;

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
        $this->commentTable($blueprint);

        $this->fluentComments($blueprint);

        $this->commentColumns($blueprint);
    }

    /**
     * Run the comment on table statement.
     * Comment set by $table->comment = 'comment';
     *
     * @param \Yajra\Oci8\Schema\OracleBlueprint $blueprint
     */
    private function commentTable(OracleBlueprint $blueprint)
    {
        $table = $this->wrapValue($blueprint->getTable());

        if ($blueprint->comment != null) {
            $this->connection->statement("comment on table {$table} is '{$blueprint->comment}'");
        }
    }

    /**
     * Wrap reserved words.
     *
     * @param string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        return $this->isReserved($value) ? parent::wrapValue($value) : $value;
    }

    /**
     * Add comments set via fluent setter.
     * Comments set by $table->string('column')->comment('comment');
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
        $table = $this->wrapValue($table);

        $column = $this->wrapValue($column);

        $this->connection->statement("comment on column {$table}.{$column} is '{$comment}'");
    }

    /**
     * Add comments on columns.
     * Comments set by $table->commentColumns = ['column' => 'comment'];
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
