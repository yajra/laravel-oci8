<?php

namespace Yajra\Oci8;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use PDO;
use PDOStatement;
use Throwable;
use Yajra\Oci8\Query\Grammars\OracleGrammar as QueryGrammar;
use Yajra\Oci8\Query\OracleBuilder as QueryBuilder;
use Yajra\Oci8\Query\Processors\OracleProcessor as Processor;
use Yajra\Oci8\Schema\Grammars\OracleGrammar as SchemaGrammar;
use Yajra\Oci8\Schema\OracleBuilder as SchemaBuilder;
use Yajra\Oci8\Schema\Sequence;
use Yajra\Oci8\Schema\Trigger;
use Yajra\Pdo\Oci8\Statement;

class Oci8Connection extends Connection
{
    const RECONNECT_ERRORS = 'reconnect_errors';

    protected string $schema;

    protected Sequence $sequence;

    protected Trigger $trigger;

    protected int $maxLength = 30;

    protected string $schemaPrefix = '';

    /**
     * @param  PDO|\Closure  $pdo
     * @param  string  $database
     * @param  string  $tablePrefix
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        $this->sequence = new Sequence($this);
        $this->trigger = new Trigger($this);
        $this->schema = $config['username'] ?? '';
        $this->maxLength = intval($config['max_name_len'] ?? 30);
        $this->schemaPrefix = $config['prefix_schema'] ?? '';
    }

    /**
     * Get the current schema.
     */
    public function getSchema(): string
    {
        return empty($this->schemaPrefix) ? $this->schema : $this->schemaPrefix;
    }

    /**
     * Set current schema.
     */
    public function setSchema(string $schema): static
    {
        $this->schema = $schema;
        $sessionVars = [
            'CURRENT_SCHEMA' => $schema,
        ];

        return $this->setSessionVars($sessionVars);
    }

    /**
     * Update oracle session variables.
     */
    public function setSessionVars(array $sessionVars): static
    {
        $vars = [];
        foreach ($sessionVars as $option => $value) {
            if (strtoupper($option) == 'CURRENT_SCHEMA' || strtoupper($option) == 'EDITION') {
                $vars[] = "$option  = $value";
            } else {
                $vars[] = "$option  = '$value'";
            }
        }

        if ($vars) {
            $sql = 'ALTER SESSION SET '.implode(' ', $vars);
            $this->statement($sql);
        }

        return $this;
    }

    /**
     * Get the oracle sequence class.
     */
    public function getSequence(): Sequence
    {
        return $this->sequence;
    }

    /**
     * Get the oracle trigger class.
     */
    public function getTrigger(): Trigger
    {
        return $this->trigger;
    }

    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new SchemaBuilder($this);
    }

    /**
     * Get a new query builder instance.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * Set oracle session date format.
     */
    public function setDateFormat(string $format = 'YYYY-MM-DD HH24:MI:SS'): static
    {
        $sessionVars = [
            'NLS_DATE_FORMAT' => $format,
            'NLS_TIMESTAMP_FORMAT' => $format,
        ];

        return $this->setSessionVars($sessionVars);
    }

    /**
     * Execute a PL/SQL Function and return its value.
     * Usage: DB::executeFunction('function_name', ['binding_1' => 'hi', 'binding_n' =>
     * 'bye'], PDO::PARAM_LOB).
     */
    public function executeFunction(
        string $functionName,
        array $bindings = [],
        int $returnType = PDO::PARAM_STR,
        ?int $length = null
    ): mixed {
        $stmt = $this->createStatementFromFunction($functionName, $bindings);

        $stmt = $this->addBindingsToStatement($stmt, $bindings);

        $stmt->bindParam(':result', $result, $returnType, $length);
        $stmt->execute();

        return $result;
    }

    /**
     * Execute a PL/SQL Procedure and return its results.
     *
     * Usage: DB::executeProcedure($procedureName, $bindings).
     * $bindings looks like:
     *         $bindings = [
     *                  'p_userid'  => $id
     *         ];
     */
    public function executeProcedure(string $procedureName, array $bindings = []): bool
    {
        $stmt = $this->createStatementFromProcedure($procedureName, $bindings);

        $stmt = $this->addBindingsToStatement($stmt, $bindings);

        return $stmt->execute();
    }

    /**
     * Execute a PL/SQL Procedure and return its cursor result.
     * Usage: DB::executeProcedureWithCursor($procedureName, $bindings).
     *
     * https://docs.oracle.com/cd/E17781_01/appdev.112/e18555/ch_six_ref_cur.htm#TDPPH218
     */
    public function executeProcedureWithCursor(
        $procedureName,
        array $bindings = [],
        string $cursorName = ':cursor'
    ): array {
        $stmt = $this->createStatementFromProcedure($procedureName, $bindings, $cursorName);

        $stmt = $this->addBindingsToStatement($stmt, $bindings);

        $cursor = null;
        $stmt->bindParam($cursorName, $cursor, PDO::PARAM_STMT);
        $stmt->execute();

        $statement = new Statement($cursor, $this->getPdo(), $this->getPdo()->getOptions());
        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_OBJ);
        $statement->closeCursor();

        return $results;
    }

    /**
     * Creates sql command to run a procedure with bindings.
     */
    public function createSqlFromProcedure(string $procedureName, array $bindings, bool|string $cursor = false): string
    {
        $paramsString = implode(',', array_map(fn ($param) => ':'.$param, array_keys($bindings)));

        $prefix = count($bindings) ? ',' : '';
        $cursor = $cursor ? $prefix.$cursor : null;

        return sprintf('begin %s(%s%s); end;', $procedureName, $paramsString, $cursor);
    }

    /**
     * Creates statement from procedure.
     */
    public function createStatementFromProcedure(
        $procedureName,
        array $bindings,
        bool|string $cursorName = false
    ): PDOStatement {
        $sql = $this->createSqlFromProcedure($procedureName, $bindings, $cursorName);

        return $this->getPdo()->prepare($sql);
    }

    /**
     * Create statement from function.
     */
    public function createStatementFromFunction(string $functionName, array $bindings): PDOStatement
    {
        $bindings = $bindings ? ':'.implode(', :', array_keys($bindings)) : '';

        $sql = sprintf('begin :result := %s(%s); end;', $functionName, $bindings);

        return $this->getPdo()->prepare($sql);
    }

    /**
     * Wrap object name with schema prefix.
     */
    public function withSchemaPrefix(string $name): string
    {
        if ($this->getSchemaPrefix()) {
            return $this->getQueryGrammar()->wrap($this->getSchemaPrefix()).'.'.$name;
        }

        return $name;
    }

    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        return new QueryGrammar($this);
    }

    /**
     * Get the schema prefix.
     */
    public function getSchemaPrefix(): string
    {
        return $this->schemaPrefix;
    }

    /**
     * Set the schema prefix.
     */
    public function setSchemaPrefix(string $prefix): static
    {
        $this->schemaPrefix = $prefix;

        return $this;
    }

    /**
     * Get the max object name length.
     */
    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * Set the object max length name settings.
     */
    public function setMaxLength(int $maxLength = 30): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return new SchemaGrammar($this);
    }

    /**
     * Get the default post processor instance.
     */
    protected function getDefaultPostProcessor(): Processor
    {
        return new Processor;
    }

    /**
     * Add bindings to statement.
     */
    public function addBindingsToStatement(PDOStatement $stmt, array $bindings): PDOStatement
    {
        foreach ($bindings as $key => &$binding) {
            $value = &$binding;
            $type = PDO::PARAM_STR;
            $length = -1;
            $options = null;

            if (is_array($binding)) {
                $value = &$binding['value'];
                $type = array_key_exists('type', $binding) ? $binding['type'] : PDO::PARAM_STR;
                $length = array_key_exists('length', $binding) ? $binding['length'] : -1;
                $options = array_key_exists('options', $binding) ? $binding['options'] : $options;
            }

            $stmt->bindParam(':'.$key, $value, $type, $length, $options);
        }

        return $stmt;
    }

    /**
     * Determine if the given exception was caused by a lost connection.
     *
     * @param  \Exception  $e
     */
    protected function causedByLostConnection(Throwable $e): bool
    {
        if (parent::causedByLostConnection($e)) {
            return true;
        }

        $lostConnectionErrors = [
            'ORA-03113',    // End-of-file on communication channel
            'ORA-03114',    // Not Connected to Oracle
            'ORA-03135',    // Connection lost contact
            'ORA-12170',    // Connect timeout occurred
            'ORA-12537',    // Connection closed
            'ORA-27146',    // Post/wait initialization failed
            'ORA-25408',    // Can not safely replay call
            'ORA-56600',    // Illegal Call
        ];

        $additionalErrors = null;

        $options = $this->config['options'] ?? [];
        if (array_key_exists(static::RECONNECT_ERRORS, $options)) {
            $additionalErrors = $this->config['options'][static::RECONNECT_ERRORS];
        }

        if (is_array($additionalErrors)) {
            $lostConnectionErrors = array_merge($lostConnectionErrors,
                $this->config['options'][static::RECONNECT_ERRORS]);
        }

        return Str::contains($e->getMessage(), $lostConnectionErrors);
    }

    /**
     * Set oracle NLS session to case insensitive search & sort.
     */
    public function useCaseInsensitiveSession(): static
    {
        return $this->setSessionVars(['NLS_COMP' => 'LINGUISTIC', 'NLS_SORT' => 'BINARY_CI']);
    }

    /**
     * Set oracle NLS session to case-sensitive search & sort.
     */
    public function useCaseSensitiveSession(): static
    {
        return $this->setSessionVars(['NLS_COMP' => 'BINARY', 'NLS_SORT' => 'BINARY']);
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param  \Yajra\Pdo\Oci8\Statement  $statement
     * @param  array  $bindings
     */
    public function bindValues($statement, $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(is_string($key) ? $key : $key + 1, $value);
        }
    }
}
