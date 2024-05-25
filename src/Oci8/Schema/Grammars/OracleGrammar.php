<?php

namespace Yajra\Oci8\Schema\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
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
     * The possible column serials.
     *
     * @var array
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * @var string
     */
    protected $schemaPrefix = '';

    /**
     * @var int
     */
    protected $maxLength = 30;

    /**
     * If this Grammar supports schema changes wrapped in a transaction.
     *
     * @var bool
     */
    protected $transactions = true;

    /**
     * Compile a create table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'create table '.$this->wrapTable($blueprint)." ( $columns";

        /*
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
     * @param  mixed  $table
     * @return string
     */
    public function wrapTable($table)
    {
        return $this->getSchemaPrefix().parent::wrapTable($table);
    }

    /**
     * Get the schema prefix.
     *
     * @return string
     */
    public function getSchemaPrefix()
    {
        return ! empty($this->schemaPrefix) ? $this->schemaPrefix.'.' : '';
    }

    /**
     * Get max length.
     *
     * @return int
     */
    public function getMaxLength()
    {
        return ! empty($this->maxLength) ? $this->maxLength : 30;
    }

    /**
     * Set the schema prefix.
     *
     * @param  string  $prefix
     */
    public function setSchemaPrefix($prefix)
    {
        $this->schemaPrefix = $prefix;
    }

    /**
     * Set max length.
     *
     * @param  int  $length
     */
    public function setMaxLength($length)
    {
        $this->maxLength = $length;
    }

    /**
     * Get the foreign key syntax for a table creation statement.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
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
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @return string|null
     */
    protected function addPrimaryKeys(Blueprint $blueprint)
    {
        $primary = $this->getCommandByName($blueprint, 'primary');

        if (! is_null($primary)) {
            $columns = $this->columnize($primary->columns);

            return ", constraint {$primary->index} primary key ( {$columns} )";
        }

        return '';
    }

    /**
     * Compile the query to determine if a table exists.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return 'select * from all_tables where upper(owner) = upper(?) and upper(table_name) = upper(?)';
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @param  string  $database
     * @param  string  $table
     * @return string
     */
    public function compileColumnExists($database, $table)
    {
        return "select column_name from all_tab_cols where upper(owner) = upper('{$database}') and upper(table_name) = upper('{$table}')";
    }

    /**
     * Compile the query to determine the columns.
     *
     * @param  string  $database
     * @param  string  $schema
     * @param  string  $table
     * @return string
     */
    public function compileColumns($schema, $table)
    {
        return "
            select
                t.column_name as name,
                nvl(t.data_type_mod, data_type) as type_name,
                null as auto_increment,
                t.data_type as type,
                t.data_length,
                t.char_length,
                t.data_precision as precision,
                t.data_scale as places,
                decode(t.nullable, 'Y', 1, 0) as nullable,
                t.data_default as \"default\",
                c.comments as \"comment\"
            from all_tab_cols t
            left join all_col_comments c on t.owner = c.owner and t.table_name = c.table_name AND t.column_name = c.column_name
            where upper(t.table_name) = upper('{$table}')
                and upper(t.owner) = upper('{$schema}')
            order by
                t.column_id
        ";
    }

    public function compileForeignKeys($schema, $table)
    {
        return "
            select
                kc.constraint_name as name,
                LISTAGG(kc.column_name, ',') WITHIN GROUP (ORDER BY kc.position) as columns,
                rc.r_owner as foreign_schema,
                kcr.table_name as foreign_table,
                LISTAGG(kcr.column_name, ',') WITHIN GROUP (ORDER BY kcr.position) as foreign_columns,
                rc.delete_rule AS \"on_delete\",
                null AS \"on_update\"
            from all_cons_columns kc
            inner join all_constraints rc ON kc.constraint_name = rc.constraint_name
            inner join all_cons_columns kcr ON kcr.constraint_name = rc.r_constraint_name
            where kc.table_name = upper('{$table}')
                and kc.owner = upper('{$schema}')
                and rc.constraint_type = 'R'
            group by
                kc.constraint_name, rc.r_owner, kcr.table_name, kc.constraint_name, rc.delete_rule
        ";
    }

    /**
     * Compile an add column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'alter table '.$this->wrapTable($blueprint)." add ( $columns";

        $sql .= (string) $this->addPrimaryKeys($blueprint);

        return $sql .= ' )';
    }

    /**
     * Compile a primary key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
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
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
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
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return 'alter table '.$this->wrapTable($blueprint)." add constraint {$command->index} unique ( ".$this->columnize($command->columns).' )';
    }

    /**
     * Compile a plain index key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return "create index {$command->index} on ".$this->wrapTable($blueprint).' ( '.$this->columnize($command->columns).' )';
    }

    /**
     * Compile a drop table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile the SQL needed to drop all tables.
     *
     * @return string
     */
    public function compileDropAllTables()
    {
        return 'BEGIN
            FOR c IN (SELECT table_name FROM user_tables) LOOP
            EXECUTE IMMEDIATE (\'DROP TABLE "\' || c.table_name || \'" CASCADE CONSTRAINTS\');
            END LOOP;

            FOR s IN (SELECT sequence_name FROM user_sequences) LOOP
            EXECUTE IMMEDIATE (\'DROP SEQUENCE \' || s.sequence_name);
            END LOOP;

            END;';
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);
        $search = str_replace('"', '', $table);

        return "declare c int;
            begin
               select count(*) into c from user_tables where upper(table_name) = upper('$search');
               if c = 1 then
                  execute immediate 'drop table $table';
               end if;
            end;";
    }

    /**
     * Compile a drop column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->wrapArray($command->columns);

        $table = $this->wrapTable($blueprint);

        return 'alter table '.$table.' drop ( '.implode(', ', $columns).' )';
    }

    /**
     * Compile a drop primary key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'primary');
    }

    /**
     * @param  Blueprint  $blueprint
     * @param  Fluent  $command
     * @param  string  $type
     * @return string
     */
    private function dropConstraint(Blueprint $blueprint, Fluent $command, $type)
    {
        $table = $this->wrapTable($blueprint);

        $index = substr($command->index, 0, $this->getMaxLength());

        if ($type === 'index') {
            return "drop index {$index}";
        }

        return "alter table {$table} drop constraint {$index}";
    }

    /**
     * Compile a drop unique key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'unique');
    }

    /**
     * Compile a drop index command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'index');
    }

    /**
     * Compile a drop foreign key command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return $this->dropConstraint($blueprint, $command, 'foreign');
    }

    /**
     * Compile a rename table command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $from = $this->wrapTable($blueprint);

        return "alter table {$from} rename to ".$this->wrapTable($command->to);
    }

    /**
     * Compile a rename column command.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @param  \Illuminate\Database\Connection  $connection
     * @return array
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $table = $this->wrapTable($blueprint);

        $rs = [];
        $rs[0] = 'alter table '.$table.' rename column '.$this->wrap($command->from).' to '.$this->wrap($command->to);

        return $rs;
    }

    /**
     * Create the column definition for a char type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return "char({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return "varchar2({$column->length})";
    }

    /**
     * Create column definition for a nvarchar type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeNvarchar2(Fluent $column)
    {
        return "nvarchar2({$column->length})";
    }

    /**
     * Create the column definition for a text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'clob';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'clob';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'clob';
    }

    /**
     * Create the column definition for a integer type.
     *
     * @param  \Illuminate\Support\Fluent  $column
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
     * @param  \Illuminate\Support\Fluent  $column
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
     * @param  \Illuminate\Support\Fluent  $column
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
     * @param  \Illuminate\Support\Fluent  $column
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
     * @param  \Illuminate\Support\Fluent  $column
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
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        return "number({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a double type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        $total = $column->total ?? 8;
        $places = $column->places ?? 2;

        return "number({$total}, {$places})";
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "number({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'char(1)';
    }

    /**
     * Create the column definition for a enum type.
     *
     * @param  \Illuminate\Support\Fluent  $column
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
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a time type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        return 'timestamp';
    }

    /**
     * Create the column definition for a timestamp type with timezone.
     *
     * @param  Fluent  $column
     * @return string
     */
    protected function typeTimestampTz(Fluent $column)
    {
        return 'timestamp with time zone';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeUuid(Fluent $column)
    {
        return 'char(36)';
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeIpAddress(Fluent $column)
    {
        return 'varchar(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeMacAddress(Fluent $column)
    {
        return 'varchar(17)';
    }

    /**
     * Create the column definition for a json type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeJson(Fluent $column)
    {
        return 'clob';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function typeJsonb(Fluent $column)
    {
        return 'clob';
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        // check if field is declared as enum
        $enum = '';
        if (count((array) $column->allowed)) {
            $columnName = $this->wrapValue($column->name);
            $enum = " check ({$columnName} in ('".implode("', '", $column->allowed)."'))";
        }

        $null = $column->nullable ? ' null' : ' not null';
        $null .= $enum;

        if (! is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default).$null;
        }

        return $null;
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        // implemented @modifyNullable
        return '';
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
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
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        $value = Str::upper($value);

        return parent::wrapValue($value);
    }

    /**
     * Compile a change column command into a series of SQL statements.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $command
     * @param  \Illuminate\Database\Connection  $connection
     * @return array|string
     *
     * @throws \RuntimeException
     */
    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $columns = [];

        foreach ($blueprint->getChangedColumns() as $column) {
            $changes = [$this->getType($column).$this->modifyCollate($blueprint, $column)];

            foreach ($this->modifiers as $modifier) {
                if ($modifier === 'Collate') {
                    continue;
                }

                if (method_exists($this, $method = "modify{$modifier}")) {
                    $constraints = (array) $this->{$method}($blueprint, $column);

                    foreach ($constraints as $constraint) {
                        $changes[] = $constraint;
                    }
                }
            }

            $columns[] = 'modify '.$this->wrap($column).' '.implode(' ', array_filter(array_map('trim', $changes)));
        }

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(' ', $columns);
    }

    /**
     * Get the SQL for a collation column modifier.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $blueprint
     * @param  \Illuminate\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->collation)) {
            return ' collate '.$this->wrapValue($column->collation);
        }
    }

    /**
     * Compile the query to determine the indexes.
     *
     * @param  string  $database
     * @param  string  $table
     * @return string
     */
    public function compileIndexes($database, $table)
    {
        return sprintf(
            'select i.index_name as name, i.column_name as columns, '
            ."a.index_type as type, decode(a.uniqueness, 'UNIQUE', 1, 0) as \"UNIQUE\" "
            .'from all_ind_columns i join ALL_INDEXES a on a.index_name = i.index_name '
            .'WHERE i.table_name = a.table_name AND i.table_owner = a.table_owner AND '
            .'i.TABLE_OWNER = upper(%s) AND i.TABLE_NAME = upper(%s) ',
            $this->quoteString($database),
            $this->quoteString($table)
        );
    }
}
