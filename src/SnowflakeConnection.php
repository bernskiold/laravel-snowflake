<?php

namespace Bernskiold\LaravelSnowflake;

use Bernskiold\LaravelSnowflake\Odbc\OdbcConnection;
use Bernskiold\LaravelSnowflake\Values\Variant;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\QueryException;
use PDO;
use PDOException;
use PDOStatement;

use function is_bool;
use function is_int;
use function is_null;
use function str_starts_with;

class SnowflakeConnection extends OdbcConnection
{
    /**
     * Temporary file path for private key
     */
    protected ?string $tempKeyFile = null;

    /**
     * Create a new database connection instance.
     *
     * @param  PDO  $pdo
     * @param  string  $database
     * @param  string  $tablePrefix
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        // On Windows we need to create a temp file for the key. Store the path for cleanup in destruct.
        if (isset($config['PRIVATE_KEY_FILE'])) {
            $this->tempKeyFile = $config['PRIVATE_KEY_FILE'];
        }
    }

    /**
     * Clean up temporary key file when connection is destroyed
     */
    public function __destruct()
    {
        if ($this->tempKeyFile && file_exists($this->tempKeyFile)) {
            unlink($this->tempKeyFile);
        }
    }

    /**
     * Drop a connection the driver has told us is broken, so the next query
     * opens a fresh one.
     *
     * Laravel heals a dead connection by matching the driver's error message
     * against a fixed list of strings, none of which Snowflake produces — it
     * reports a broken session as "Request returned as being unsuccessful",
     * its catch-all for a response it could not parse. So a session that dies
     * under a long-lived process (a queue worker, above all) is never
     * re-established: the dead PDO stays cached on this connection and every
     * query after it fails instantly, until someone restarts the process.
     *
     * The query itself is still failed rather than replayed. Retrying it here
     * would be silent statement replay, and a write whose response was lost
     * after it had already run would be applied twice. Dropping the connection
     * and letting the caller retry gets the same recovery without that risk.
     */
    protected function handleQueryException(QueryException $e, $query, $bindings, Closure $callback)
    {
        if ($this->causedByBrokenConnection($e)) {
            $this->disconnect();
        }

        return parent::handleQueryException($e, $query, $bindings, $callback);
    }

    /**
     * Whether the driver is reporting a connection-level failure rather than
     * something wrong with the statement.
     *
     * Read from the SQLSTATE class rather than the message: `08` is the
     * standard "connection exception" class, which is what the Snowflake
     * driver reports for a session it can no longer use, and it does not
     * depend on error wording surviving a driver upgrade.
     */
    protected function causedByBrokenConnection(QueryException $e): bool
    {
        $previous = $e->getPrevious();

        $sqlState = $previous instanceof PDOException && isset($previous->errorInfo[0])
            ? (string) $previous->errorInfo[0]
            : (string) $e->getCode();

        return str_starts_with($sqlState, '08');
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new Schema\Builder($this);
    }

    public function getDefaultQueryGrammar()
    {
        $queryGrammar = $this->getConfig('options.grammar.query');

        if ($queryGrammar) {
            return new $queryGrammar($this);
        }

        return new Grammars\QueryGrammar($this);
    }

    public function getDefaultSchemaGrammar()
    {
        $schemaGrammar = $this->getConfig('options.grammar.schema');

        if ($schemaGrammar) {
            return new $schemaGrammar($this);
        }

        return new Grammars\SchemaGrammar($this);
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * Booleans are bound as the literals TRUE/FALSE, which Snowflake coerces
     * into its native boolean type.
     *
     * @param  PDOStatement  $statement
     * @param  array  $bindings
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $parameter = is_string($key) ? $key : $key + 1;

            if (is_bool($value)) {
                $statement->bindValue($parameter, $value ? 'TRUE' : 'FALSE', PDO::PARAM_STR);
            } elseif (is_null($value)) {
                $statement->bindValue($parameter, null, PDO::PARAM_NULL);
            } elseif (is_int($value)) {
                $statement->bindValue($parameter, $value, PDO::PARAM_INT);
            } else {
                $statement->bindValue($parameter, $value, PDO::PARAM_STR);
            }
        }
    }

    /**
     * Prepare the query bindings for execution.
     *
     * Values are passed through untouched apart from dates, so that numeric
     * strings (including those with leading zeros or decimals) are never
     * silently coerced.
     *
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof Variant) {
                $bindings[$key] = $value->toJsonString();
            } elseif ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            }
        }

        return $bindings;
    }

    /**
     * Get the default post processor instance.
     *
     * @return Processor
     */
    protected function getDefaultPostProcessor()
    {
        $processor = $this->getConfig('options.processor');

        if ($processor) {
            return new $processor;
        }

        return new SnowflakeProcessor;
    }
}
