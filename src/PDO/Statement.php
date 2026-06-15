<?php

namespace Bernskiold\LaravelSnowflake\PDO;

use const FILTER_VALIDATE_BOOLEAN;

use Illuminate\Support\Str;
use PDO;
use PDOStatement;

use function count;
use function is_bool;
use function is_float;
use function is_int;
use function is_null;
use function is_string;

/**
 * Statement class for Snowflake ODBC connections.
 *
 * The Snowflake ODBC driver does not support streaming bind values, so the
 * bindings are collected and interpolated into the query before it is
 * prepared. The native pdo_snowflake driver does not use this class.
 */
class Statement extends PDOStatement
{
    protected ?PDO $pdo = null;

    protected ?PDOStatement $exec = null;

    protected array $bindings = [];

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function bindValue($parameter, $value, $type = null): bool
    {
        $type = $value === null ? PDO::PARAM_NULL : $type;
        $this->bindings[$parameter] = [$value, $type];

        return true;
    }

    public function bindParam($parameter, &$value, $type = null, $maxlen = null, $driverdata = null): bool
    {
        $this->bindings[$parameter] = [$value, $type];

        return true;
    }

    public function execute(?array $params = null): bool
    {
        if (! empty($params)) {
            $index = 1;

            foreach ($params as $key => $value) {
                $this->bindValue(is_string($key) ? $key : $index++, $value);
            }
        }

        $segments = explode('?', $this->queryString);

        if (count($segments) > 1) {
            $bindings = $this->_prepareValues();

            $query = '';
            for ($i = 0; $i < count($segments); $i++) {
                $query .= ($bindings[$i] ?? '').$segments[$i];
            }
        } else {
            $query = reset($segments);
        }

        // The Snowflake ODBC driver executes DDL during prepare, so run it
        // directly instead of preparing it twice.
        if (Str::startsWith(strtoupper(trim($query)), ['CREATE', 'ALTER', 'DROP', 'TRUNCATE'])) {
            return $this->pdo->exec($query) !== false;
        }

        // reset PDO Statement for "parent"
        $this->exec = $this->pdo->prepare($query, [PDO::ATTR_STATEMENT_CLASS => [PDOStatement::class]]);

        return $this->exec->execute();
    }

    /**
     * Render the collected bindings as SQL literals, keyed for positional
     * interpolation (binding 1 precedes the first query segment break).
     */
    protected function _prepareValues(): array
    {
        $bindings = [];

        foreach ($this->bindings as $key => $param) {
            [$val, $type] = $param;

            if (is_null($val) || $type === PDO::PARAM_NULL) {
                $val = 'null';
            } elseif (is_bool($val) || $type === PDO::PARAM_BOOL) {
                $val = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE';
            } elseif (is_int($val) || is_float($val)) {
                $val = (string) $val;
            } elseif ($type === PDO::PARAM_INT) {
                $val = (string) (int) $val;
            } else {
                // Backslash is an escape character inside Snowflake string
                // literals, so it has to be escaped along with the quote.
                $val = "'".str_replace(['\\', "'"], ['\\\\', "''"], (string) $val)."'";
            }

            $bindings[$key] = $val;
        }

        return $bindings;
    }

    public function columnCount(): int
    {
        return $this->exec ? $this->exec->columnCount() : parent::columnCount();
    }

    public function rowCount(): int
    {
        return $this->exec ? $this->exec->rowCount() : parent::rowCount();
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->exec
            ? $this->exec->fetch($mode, $cursorOrientation, $cursorOffset)
            : parent::fetch($mode, $cursorOrientation, $cursorOffset);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->exec
            ? $this->exec->fetchAll($mode, ...$args)
            : parent::fetchAll($mode, ...$args);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->exec ? $this->exec->fetchColumn($column) : parent::fetchColumn($column);
    }

    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        return $this->exec
            ? $this->exec->fetchObject($class, $constructorArgs)
            : parent::fetchObject($class, $constructorArgs);
    }
}
