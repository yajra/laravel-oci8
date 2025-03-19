<?php

namespace Yajra\Oci8\Schema\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\OracleReservedWords;

/**
 * @property Oci8Connection $connection
 */
class OracleGrammar extends Grammar
{
    use OracleReservedWords;

    /**
     * The keyword identifier wrapper format.
     */
    protected string $wrapper = '%s';

    /**
     * The possible column modifiers.
     *
     * @var array
     */
    protected $modifiers = ['Increment', 'Nullable', 'Default'];

    /**
     * The possible column serials.
     */
    protected array $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * If this Grammar supports schema changes wrapped in a transaction.
     */
    protected $transactions = true;

    /**
     * Compile a create table command.
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command): string
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'create table '.$this->wrapTable($blueprint)." ( $columns";

        /*
         * To be able to name the primary/foreign keys when the table is
         * initially created we will need to check for a primary/foreign
         * key commands and add the columns to the table's declaration
         * here so they can be created on the tables.
         */
        $sql .= $this->addForeignKeys($blueprint);

        $sql .= $this->addPrimaryKeys($blueprint);

        $sql .= ' )';

        return $sql;
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  mixed  $table
     * @param  string|null  $prefix
     */
    public function wrapTable($table, $prefix = null): string
    {
        if ($this->getSchemaPrefix()) {
            return $this->getSchemaPrefix().'.'.parent::wrapTable($table);
        }

        return parent::wrapTable($table);
    }

    /**
     * Get the schema prefix.
     */
    public function getSchemaPrefix(): string
    {
        if ($this->connection->getSchemaPrefix()) {
            return $this->wrap($this->connection->getSchemaPrefix());
        }

        return '';
    }

    /**
     * Get max object name length.
     */
    public function getMaxLength(): int
    {
        return $this->connection->getMaxLength();
    }

    /**
     * Set the schema prefix.
     */
    public function setSchemaPrefix(string $prefix): void
    {
        $this->connection->setSchemaPrefix($prefix);
    }

    /**
     * Set the max object name length.
     */
    public function setMaxLength(int $length): void
    {
        $this->connection->setMaxLength($length);
    }

    /**
     * Get the foreign key syntax for a table creation statement.
     */
    protected function addForeignKeys(Blueprint $blueprint): string
    {
        $sql = '';

        $foreignKeys = $this->getCommandsByName($blueprint, 'foreign');

        // Once we have all the foreign key commands for the table creation statement
        // we'll loop through each of them and add them to the create table SQL we
        // are building
        foreach ($foreignKeys as $foreign) {
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
     */
    protected function addPrimaryKeys(Blueprint $blueprint): ?string
    {
        $primary = $this->getCommandByName($blueprint, 'primary');

        if (! is_null($primary)) {
            $columns = $this->columnize($primary->columns);

            return ", constraint {$primary->index} primary key ( {$columns} )";
        }

        return null;
    }

    /**
     * Compile the query to determine if a table exists.
     */
    public function compileTableExists($schema, $table): ?string
    {
        return sprintf(
            'select count(*) from all_tables where upper(owner) = upper(%s) and upper(table_name) = upper(%s)',
            $this->quoteString($schema),
            $this->quoteString($table)
        );
    }

    /**
     * Compile the query to determine the list of columns.
     */
    public function compileColumnExists(string $database, string $table): string
    {
        return "select column_name from all_tab_cols where upper(owner) = upper('{$database}') and upper(table_name) = upper('{$table}') order by column_id";
    }

    /**
     * Compile the query to determine the columns.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compileColumns($schema, $table): string
    {
        $schema ??= $this->connection->getConfig('username');

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

    /**
     * Compile the query to determine the foreign keys.
     *
     * @param  string|null  $schema
     * @param  string  $table
     */
    public function compileForeignKeys($schema, $table): string
    {
        $schema ??= $this->connection->getConfig('username');

        return sprintf("
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
            where kc.table_name = upper(%s)
                and rc.r_owner = upper(%s)
                and rc.constraint_type = 'R'
            group by
                kc.constraint_name, rc.r_owner, kcr.table_name, kc.constraint_name, rc.delete_rule
        ", $this->quoteString($table), $this->quoteString($schema));
    }

    /**
     * Compile an add column command.
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf('alter table %s add ( %s )',
            $this->wrapTable($blueprint),
            $this->getColumn($blueprint, $command->column)
        );
    }

    /**
     * Compile a primary key command.
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command): ?string
    {
        $create = $this->getCommandByName($blueprint, 'create');

        if (is_null($create)) {
            $columns = $this->columnize($command->columns);

            $table = $this->wrapTable($blueprint);

            return "alter table {$table} add constraint {$command->index} primary key ({$columns})";
        }

        return null;
    }

    /**
     * Compile a foreign key command.
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command): ?string
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

        return null;
    }

    /**
     * Compile a unique key command.
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        return 'alter table '.$this->wrapTable($blueprint)." add constraint {$command->index} unique ( ".$this->columnize($command->columns).' )';
    }

    /**
     * Compile a plain index key command.
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        return "create index {$command->index} on ".$this->wrapTable($blueprint).' ( '.$this->columnize($command->columns).' )';
    }

    /**
     * Compile a fulltext index key command.
     */
    public function compileFullText(Blueprint $blueprint, Fluent $command): string
    {
        $tableName = $this->wrapTable($blueprint);
        $columns = $command->columns;
        $indexBaseName = $command->index;
        $preferenceName = $indexBaseName.'_preference';

        $sqlStatements = [];

        foreach ($columns as $key => $column) {
            $indexName = $indexBaseName;
            $parametersIndex = '';

            if (count($columns) > 1) {
                $indexName .= "_{$key}";
                $parametersIndex = "datastore {$preferenceName} ";
            }

            $parametersIndex .= 'sync(on commit)';

            $sql = "execute immediate 'create index {$indexName} on $tableName ($column) indextype is ctxsys.context parameters (''$parametersIndex'')';";

            $sqlStatements[] = $sql;
        }

        return 'begin '.implode(' ', $sqlStatements).' end;';
    }

    /**
     * Compile a drop table command.
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile the SQL needed to drop all tables.
     */
    public function compileDropAllTables(): string
    {
        return 'BEGIN
            FOR c IN (SELECT table_name FROM user_tables WHERE secondary = \'N\') LOOP
            EXECUTE IMMEDIATE (\'DROP TABLE "\' || c.table_name || \'" CASCADE CONSTRAINTS\');
            END LOOP;

            FOR s IN (SELECT sequence_name FROM user_sequences) LOOP
            EXECUTE IMMEDIATE (\'DROP SEQUENCE \' || s.sequence_name);
            END LOOP;

            END;';
    }

    /**
     * Compile a drop table (if exists) command.
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        $table = $this->wrapTable($blueprint);

        return "begin execute immediate 'drop table $table'; exception when others then null; end;";
    }

    /**
     * Compile a drop column command.
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->wrapArray($command->columns);

        $table = $this->wrapTable($blueprint);

        return 'alter table '.$table.' drop ( '.implode(', ', $columns).' )';
    }

    /**
     * Compile a drop primary key command.
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command): string
    {
        return $this->dropConstraint($blueprint, $command, 'primary');
    }

    /**
     * Drop a constraint from the table.
     */
    private function dropConstraint(Blueprint $blueprint, Fluent $command, string $type): string
    {
        $table = $this->wrapTable($blueprint);

        $index = mb_substr($command->index, 0, $this->getMaxLength());

        if ($type === 'index') {
            return "drop index {$index}";
        }

        return "alter table {$table} drop constraint {$index}";
    }

    /**
     * Compile a drop unique key command.
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        return $this->dropConstraint($blueprint, $command, 'unique');
    }

    /**
     * Compile a drop index command.
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        return $this->dropConstraint($blueprint, $command, 'index');
    }

    /**
     * Compile a drop foreign key command.
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command): string
    {
        return $this->dropConstraint($blueprint, $command, 'foreign');
    }

    /**
     * Compile a drop fulltext index command.
     */
    public function compileDropFullText(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $command->columns;

        if (empty($columns)) {
            return $this->compileDropIndex($blueprint, $command);
        }

        $columns = array_map(fn ($column) => "'".strtoupper((string) $column)."'", $columns);
        $columns = implode(', ', $columns);

        $dropFullTextSql = "for idx_rec in (select idx_name from ctx_user_indexes where idx_text_name in ($columns)) loop
            execute immediate 'drop index ' || idx_rec.idx_name;
        end loop;";

        return "begin $dropFullTextSql end;";
    }

    /**
     * Compile a rename table command.
     */
    public function compileRename(Blueprint $blueprint, Fluent $command): string
    {
        $from = $this->wrapTable($blueprint);

        return "alter table {$from} rename to ".$this->wrapTable($command->to);
    }

    /**
     * Compile a rename column command.
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command): array
    {
        $table = $this->wrapTable($blueprint);

        $rs = [];
        $rs[0] = 'alter table '.$table.' rename column '.$this->wrap($command->from).' to '.$this->wrap($command->to);

        return $rs;
    }

    /**
     * Create the column definition for a char type.
     */
    protected function typeChar(Fluent $column): string
    {
        return "char({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     */
    protected function typeString(Fluent $column): string
    {
        return "varchar2({$column->length})";
    }

    /**
     * Create column definition for a nvarchar type.
     */
    protected function typeNvarchar2(Fluent $column): string
    {
        return "nvarchar2({$column->length})";
    }

    /**
     * Create the column definition for a text type.
     */
    protected function typeText(Fluent $column): string
    {
        return 'clob';
    }

    /**
     * Create the column definition for a medium text type.
     */
    protected function typeMediumText(Fluent $column): string
    {
        return 'clob';
    }

    /**
     * Create the column definition for a long text type.
     */
    protected function typeLongText(Fluent $column): string
    {
        return 'clob';
    }

    /**
     * Create the column definition for a integer type.
     */
    protected function typeInteger(Fluent $column): string
    {
        $length = $column->length ?: 10;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a integer type.
     */
    protected function typeBigInteger(Fluent $column): string
    {
        $length = $column->length ?: 19;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a medium integer type.
     */
    protected function typeMediumInteger(Fluent $column): string
    {
        $length = $column->length ?: 7;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a small integer type.
     */
    protected function typeSmallInteger(Fluent $column): string
    {
        $length = $column->length ?: 5;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a tiny integer type.
     */
    protected function typeTinyInteger(Fluent $column): string
    {
        $length = $column->length ?: 3;

        return "number({$length},0)";
    }

    /**
     * Create the column definition for a float type.
     */
    protected function typeFloat(Fluent $column): string
    {
        if ($column->precision) {
            return "float({$column->precision})";
        }

        return 'float(126)';
    }

    /**
     * Create the column definition for a double type.
     */
    protected function typeDouble(Fluent $column): string
    {
        return 'float(126)';
    }

    /**
     * Create the column definition for a decimal type.
     */
    protected function typeDecimal(Fluent $column): string
    {
        return "number({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     */
    protected function typeBoolean(Fluent $column): string
    {
        return 'char(1)';
    }

    /**
     * Create the column definition for a enum type.
     */
    protected function typeEnum(Fluent $column): string
    {
        $length = $column->length ?: 255;

        return "varchar2({$length})";
    }

    /**
     * Create the column definition for a date type.
     */
    protected function typeDate(Fluent $column): string
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     */
    protected function typeDateTime(Fluent $column): string
    {
        return 'date';
    }

    /**
     * Create the column definition for a time type.
     */
    protected function typeTime(Fluent $column): string
    {
        return 'date';
    }

    /**
     * Create the column definition for a timestamp type.
     */
    protected function typeTimestamp(Fluent $column): string
    {
        return 'timestamp';
    }

    /**
     * Create the column definition for a timestamp type with timezone.
     */
    protected function typeTimestampTz(Fluent $column): string
    {
        return 'timestamp with time zone';
    }

    /**
     * Create the column definition for a binary type.
     */
    protected function typeBinary(Fluent $column): string
    {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type.
     */
    protected function typeUuid(Fluent $column): string
    {
        return 'char(36)';
    }

    /**
     * Create the column definition for an IP address type.
     */
    protected function typeIpAddress(Fluent $column): string
    {
        return 'varchar(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     */
    protected function typeMacAddress(Fluent $column): string
    {
        return 'varchar(17)';
    }

    /**
     * Create the column definition for a json type.
     */
    protected function typeJson(Fluent $column): string
    {
        return 'clob';
    }

    /**
     * Create the column definition for a jsonb type.
     */
    protected function typeJsonb(Fluent $column): string
    {
        return 'clob';
    }

    /**
     * Get the SQL for a nullable column modifier.
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column): string
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
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column): string
    {
        // implemented @modifyNullable
        return '';
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column): ?string
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            $blueprint->primary($column->name);
        }

        return null;
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     */
    protected function wrapValue($value): string
    {
        $value = Str::upper($value);

        return parent::wrapValue($value);
    }

    /**
     * Compile a change column command into a series of SQL statements.
     */
    public function compileChange(Blueprint $blueprint, Fluent $command): array|string
    {
        $columns = [];

        $column = $command->column;

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

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(' ', $columns);
    }

    /**
     * Get the SQL for a collation column modifier.
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column): ?string
    {
        if (! is_null($column->collation)) {
            return ' collate '.$this->wrapValue($column->collation);
        }

        return null;
    }

    /**
     * Compile the query to determine the indexes.
     *
     * @param  string  $schema
     * @param  string  $table
     */
    public function compileIndexes($schema, $table): string
    {
        return sprintf(
            'select i.index_name as name, i.column_name as columns, '
            ."a.index_type as type, decode(a.uniqueness, 'UNIQUE', 1, 0) as \"UNIQUE\" "
            .'from all_ind_columns i join ALL_INDEXES a on a.index_name = i.index_name '
            .'WHERE i.table_name = a.table_name AND i.table_owner = a.table_owner AND '
            .'i.TABLE_OWNER = upper(%s) AND i.TABLE_NAME = upper(%s) ',
            $this->quoteString($schema),
            $this->quoteString($table)
        );
    }

    /**
     * Compile the command to enable foreign key constraints.
     */
    public function compileEnableForeignKeyConstraints(string $owner): string
    {
        return $this->compileForeignKeyConstraints($owner, 'enable');
    }

    /**
     * Compile the command to disable foreign key constraints.
     */
    public function compileDisableForeignKeyConstraints(string $owner): string
    {
        return $this->compileForeignKeyConstraints($owner, 'disable');
    }

    /**
     * Compile foreign key constraints with enable or disable action.
     */
    public function compileForeignKeyConstraints(string $owner, string $action): string
    {
        return 'begin
            for s in (
                SELECT \'alter table \' || c2.table_name || \' '.$action.' constraint \' || c2.constraint_name as statement
                FROM all_constraints c
                         INNER JOIN all_constraints c2
                                    ON (c.constraint_name = c2.r_constraint_name AND c.owner = c2.owner)
                         INNER JOIN all_cons_columns col
                                    ON (c.constraint_name = col.constraint_name AND c.owner = col.owner)
                WHERE c2.constraint_type = \'R\'
                  AND c.owner = \''.strtoupper($owner).'\'
                )
                loop
                    execute immediate s.statement;
                end loop;
        end;';
    }

    /**
     * Compile the query to determine the tables.
     *
     * @param  string  $schema
     */
    public function compileTables($schema): string
    {
        return 'select lower(all_tab_comments.table_name)  as "name",
                lower(all_tables.owner) as "schema",
                sum(user_segments.bytes) as "size",
                all_tab_comments.comments as "comments",
                (select lower(value) from nls_database_parameters where parameter = \'NLS_SORT\') as "collation"
            from all_tables
                join all_tab_comments on all_tab_comments.table_name = all_tables.table_name
                left join user_segments on user_segments.segment_name = all_tables.table_name
            where all_tables.owner = \''.strtoupper($schema).'\'
                and all_tab_comments.owner = \''.strtoupper($schema).'\'
                and all_tab_comments.table_type in (\'TABLE\')
            group by all_tab_comments.table_name, all_tables.owner, all_tables.num_rows,
                all_tables.avg_row_len, all_tables.blocks, all_tab_comments.comments
            order by all_tab_comments.table_name';
    }
}
