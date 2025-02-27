<?php

namespace Yajra\Oci8\Schema;

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
     * Set table and column comments.
     */
    public function setComments(OracleBlueprint $blueprint): void
    {
        $this->commentTable($blueprint);

        $this->fluentComments($blueprint);

        $this->commentColumns($blueprint);
    }

    /**
     * Run the comment on table statement.
     * Comment set by $table->comment = 'comment';.
     */
    private function commentTable(OracleBlueprint $blueprint): void
    {
        $table = $this->wrapValue($blueprint->getTable());

        if ($blueprint->comment != null) {
            $this->connection->statement("comment on table {$table} is '{$blueprint->comment}'");
        }
    }

    /**
     * Wrap reserved words.
     *
     * @param  string  $value
     */
    protected function wrapValue($value): string
    {
        return $this->isReserved($value) ? parent::wrapValue($value) : $value;
    }

    /**
     * Add comments set via fluent setter.
     * Comments set by $table->string('column')->comment('comment');.
     */
    private function fluentComments(OracleBlueprint $blueprint): void
    {
        foreach ($blueprint->getColumns() as $column) {
            if (isset($column['comment'])) {
                $this->commentColumn($blueprint->getTable(), $column['name'], $column['comment']);
            }
        }
    }

    /**
     * Run the comment on column statement.
     */
    private function commentColumn(string $table, string $column, string $comment): void
    {
        $table = $this->wrapValue($table);
        $table = $this->connection->getTablePrefix().$table;
        $column = $this->wrapValue($column);

        $this->connection->statement("comment on column {$table}.{$column} is '{$comment}'");
    }

    /**
     * Add comments on columns.
     * Comments set by $table->commentColumns = ['column' => 'comment'];.
     */
    private function commentColumns(OracleBlueprint $blueprint): void
    {
        foreach ($blueprint->commentColumns as $column => $comment) {
            $this->commentColumn($blueprint->getTable(), $column, $comment);
        }
    }
}
