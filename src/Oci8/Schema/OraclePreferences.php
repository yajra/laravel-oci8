<?php

namespace Yajra\Oci8\Schema;

use Illuminate\Database\Schema\Blueprint;
use Yajra\Oci8\Oci8Connection;

/**
 * @see https://docs.oracle.com/en/database/oracle/oracle-database/19/ccref/CTX_DDL-package.html#GUID-0F7C39E8-E44A-421C-B40D-3B3578B507E9
 */
class OraclePreferences
{
    protected array $columns = [];

    protected array $preferenceName = [];

    public function __construct(protected Oci8Connection $connection) {}

    /**
     * Create a preferences values to use in index fullText.
     */
    public function createPreferences(Blueprint $blueprint): void
    {
        $this->setPreferenceFullText($blueprint);

        $sql = $this->generateSqlCreatePreferences();

        if (! empty($sql)) {
            $this->connection->statement(
                "BEGIN $sql END;"
            );
        }
    }

    /**
     * Generate script sql to create preferences.
     */
    protected function generateSqlCreatePreferences(
        ?string $objectNameOracle = 'MULTI_COLUMN_DATASTORE',
        ?string $attributeNameOracle = 'COLUMNS'
    ): string {
        $ctxDdlCreatePreferences = [];

        foreach ($this->columns as $key => $columns) {
            $preferenceName = $this->preferenceName[$key];
            $formattedColumns = $this->formatMultipleCtxColumns($columns);

            $ctxDdlCreatePreferences[] = "ctx_ddl.create_preference('{$preferenceName}', '{$objectNameOracle}');
                ctx_ddl.set_attribute('{$preferenceName}', '{$attributeNameOracle}', '{$formattedColumns}');";
        }

        return implode(' ', $ctxDdlCreatePreferences);
    }

    /**
     * Set columns and preference name to class attributes.
     */
    public function setPreferenceFullText(Blueprint $blueprint): void
    {
        $this->columns = [];
        $this->preferenceName = [];

        foreach ($blueprint->getCommands() as $value) {
            if ($value['name'] === 'fulltext' && count($value['columns']) > 1) {
                $this->columns[] = $value['columns'];
                $this->preferenceName[] = $value['index'].'_preference';
            }
        }
    }

    /**
     * Format with "implode" function columns to use in preferences.
     */
    protected function formatMultipleCtxColumns(array $columns): string
    {
        return implode(', ', $columns);
    }

    /**
     * Drop preferences by specified table.
     */
    public function dropPreferencesByTable(string $table): void
    {
        $sqlDropPreferencesByTable = "BEGIN
                FOR c IN (select distinct (substr(cui.idx_name, 1, instr(cui.idx_name, '_', -1, 1) - 1) || '_preference') preference
                        from
                            ctxsys.ctx_user_indexes cui
                        where
                            cui.idx_table = ?) LOOP
                    EXECUTE IMMEDIATE 'BEGIN ctx_ddl.drop_preference(:preference); END;'
                    USING c.preference;
                END LOOP;
            END;";

        $this->connection->statement($sqlDropPreferencesByTable, [
            strtoupper($table),
        ]);
    }

    /**
     * Drop all user preferences.
     */
    public function dropAllPreferences(): void
    {
        $sqlDropAllPreferences = "BEGIN
                FOR c IN (SELECT pre_name FROM ctx_user_preferences) LOOP
                    EXECUTE IMMEDIATE 'BEGIN ctx_ddl.drop_preference(:pre_name); END;'
                    USING c.pre_name;
                END LOOP;
            END;";

        $this->connection->statement($sqlDropAllPreferences);
    }
}
