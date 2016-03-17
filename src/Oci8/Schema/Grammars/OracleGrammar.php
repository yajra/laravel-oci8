<?php

namespace Yajra\Oci8\Schema\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Yajra\Oci8\OracleReservedWords;

class OracleGrammar extends Grammar
{
    use OracleReservedWords;

    /**
     * The keyword identifier wrapper format.
     *
     * @var string
     */
    protected $wrapper = '%s';

    /**
     * The possible column modifiers.
     *
     * @var array
     */
    protected $modifiers = ['Increment', 'Nullable', 'Default'];

    /**
     * The possible column serials
     *
     * @var array
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * @var string
     */
    protected $schema_prefix = '';

    /**
     * Compile a create table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'create table ' . $this->wrapTable($blueprint) . " ( $columns";

        /**
         * To be able to name the primary/foreign keys when the table is
         * initially created we will need to check for a primary/foreign
         * key commands and add the columns to the table's declaration
         * here so they can be created on the tables.
         */
        $sql .= (string) $this->addForeignKeys($blueprint);

        $sql .= (string) $this->addPrimaryKeys($blueprint);

        $sql .= ' )';

        return $sql;
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  mixed $table
     * @return string
     */
    public function wrapTable($table)
    {
        return $this->getSchemaPrefix() . parent::wrapTable($table);
    }

    /**
     * Return the schema prefix
     *
     * @return string
     */
    public function getSchemaPrefix()
    {
        return ! empty($this->schema_prefix) ? $this->schema_prefix . '.' : '';
    }

    /**
     * Set the shema prefix
     *
     * @param string $prefix
     */
    public function setSchemaPrefix($prefix)
    {
        $this->schema_prefix = $prefix;
    }

    /**
     * Get the foreign key syntax for a table creation statement.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @return string
     */
    protected function addForeignKeys(Blueprint $blueprint)
    {
        $sql = '';

        $foreigns = $this->getCommandsByName($blueprint, 'foreign');

        // Once we have all the foreign key commands for the table creation statement
        // we'll loop through each of them and add them to the create table SQL we
        // are building
        foreach ($foreigns as $foreign) {
            $on = $this->wrapTable($foreign->on);

            $columns = $this->columnize($foreign->columns);

            $onColumns = $this->columnize((array) $foreign->references);

            $sql .= ", constraint {$foreign->index} foreign key ( {$columns} ) references {$on} ( {$onColumns} )";

            // Once we have the basic foreign key creation statement constructed we can
            // build out the syntax for what should happen on an update or delete of
            // the affected columns, which will get something like "cascade", etc.
            if (! is_null($foreign->onDelete)) {
                $sql .= " on delete {$foreign->onDelete}";
            }
        }

        return $sql;
    }

    /**
     * Get the primary key syntax for a table creation statement.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @return string|null
     */
    protected function addPrimaryKeys(Blueprint $blueprint)
    {
        $primary = $this->getCommandByName($blueprint, 'primary');

        if (! is_null($primary)) {
            $columns = $this->columnize($primary->columns);

            return ", constraint {$primary->index} primary key ( {$columns} )";
        }

        return "";
    }

    /**
     * Compile the query to determine if a table exists.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return "select * from user_tables where upper(table_name) = upper(?)";
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @return string
     */
    public function compileColumnExists($table)
    {
        return "select column_name from user_tab_columns where table_name = upper('" . $table . "')";
    }

    /**
     * Compile an add column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'alter table ' . $this->wrapTable($blueprint) . " add ( $columns";

        $sql .= (string) $this->addPrimaryKeys($blueprint);

        return $sql .= ' )';
    }

    /**
     * Compile a primary key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        $create = $this->getCommandByName($blueprint, 'create');

        if (is_null($create)) {
            $columns = $this->columnize($command->columns);

            $table = $this->wrapTable($blueprint);

            return "alter table {$table} add constraint {$command->index} primary key ({$columns})";
        }
    }

    /**
     * Compile a foreign key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string|void
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        $create = $this->getCommandByName($blueprint, 'create');

        if (is_null($create)) {
            $table = $this->wrapTable($blueprint);

            $on = $this->wrapTable($command->on);

            // We need to prepare several of the elements of the foreign key definition
            // before we can create the SQL, such as wrapping the tables and convert
            // an array of columns to comma-delimited strings for the SQL queries.
            $columns = $this->columnize($command->columns);

            $onColumns = $this->columnize((array) $command->references);

            $sql = "alter table {$table} add constraint {$command->index} ";

            $sql .= "foreign key ( {$columns} ) references {$on} ( {$onColumns} )";

            // Once we have the basic foreign key creation statement constructed we can
            // build out the syntax for what should happen on an update or delete of
            // the affected columns, which will get something like "cascade", etc.
            if (! is_null($command->onDelete)) {
                $sql .= " on delete {$command->onDelete}";
            }

            return $sql;
        }
    }

    /**
     * Compile a unique key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return "alter table " . $this->wrapTable($blueprint) . " add constraint {$command->index} unique ( " . $this->columnize($command->columns) . " )";
    }

    /**
     * Compile a plain index key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return "create index {$command->index} on " . $this->wrapTable($blueprint) . " ( " . $this->columnize($command->columns) . " )";
    }

    /**
     * Compile a drop table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        return "declare c int;
            begin
               select count(*) into c from user_tables where table_name = upper('$table');
               if c = 1 then
                  execute immediate 'drop table $table';
               end if;
            end;";
    }

    /**
     * Compile a drop column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->wrapArray($command->columns);

        $table = $this->wrapTable($blueprint);

        return 'alter table ' . $table . ' drop ( ' . implode(', ', $columns) . ' )';
    }

    /**
     * Compile a drop primary key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'primary');
    }

    /**
     * @param Blueprint $blueprint
     * @param Fluent $command
     * @param string $type
     * @return string
     */
    private function dropConstraint(Blueprint $blueprint, Fluent $command, $type)
    {
        $table = $this->wrapTable($blueprint);
        $index = substr($command->index, 0, 30);

        if ($type === 'index') {
            return "drop index {$index}";
        }

        return "alter table {$table} drop constraint {$index}";
    }

    /**
     * Compile a drop unique key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'unique');
    }

    /**
     * Compile a drop index command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'index');
    }

    /**
     * Compile a drop foreign key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'foreign');
    }

    /**
     * Compile a rename table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $from = $this->wrapTable($blueprint);

        return "alter table {$from} rename to " . $this->wrapTable($command->to);
    }

    /**
     * Compile a rename column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $command
     * @param  \Illuminate\Database\Connection $connection
     * @return array
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $table = $this->wrapTable($blueprint);

        $rs    = [];
        $rs[0] = 'alter table ' . $table . ' rename column ' . $command->from . ' to ' . $command->to;

        return (array) $rs;
    }

    /**
     * Create the column definition for a char type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return "char({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return "varchar2({$column->length})";
    }

    /**
     * Create the column definition for a text type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return "clob";
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'clob';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'clob';
    }

    /**
     * Create the column definition for a integer type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        $length = ($column->length) ? $column->length : 10;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a integer type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        $length = ($column->length) ? $column->length : 19;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        $length = ($column->length) ? $column->length : 7;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        $length = ($column->length) ? $column->length : 5;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        $length = ($column->length) ? $column->length : 3;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a float type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        return "number({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a double type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        return "number({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "number({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return "char(1)";
    }

    /**
     * Create the column definition for a enum type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        $length = ($column->length) ? $column->length : 255;

        return "varchar2({$length})";
    }

    /**
     * Create the column definition for a date type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a time type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        return 'timestamp';
    }

    /**
     * Create the column definition for a timestamp type with timezone.
     *
     * @param Fluent $column
     * @return string
     */
    protected function typeTimestampTz(Fluent $column)
    {
        return 'timestamp with time zone';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'blob';
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        // check if field is declared as enum
        $enum = "";
        if (count((array) $column->allowed)) {
            $enum = " check ({$column->name} in ('" . implode("', '", $column->allowed) . "'))";
        }

        $null = $column->nullable ? ' null' : ' not null';
        $null .= $enum;

        if (! is_null($column->default)) {
            return " default " . $this->getDefaultValue($column->default) . $null;
        }

        return $null;
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        // implemented @modifyNullable
        return "";
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            $blueprint->primary($column->name);
        }
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($this->isReserved($value)) {
            return parent::wrapValue($value);
        }

        return $value !== '*' ? sprintf($this->wrapper, $value) : $value;
    }
}
