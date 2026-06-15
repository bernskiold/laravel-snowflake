<?php

namespace Bernskiold\LaravelSnowflake\Grammars;

use Bernskiold\LaravelSnowflake\Concerns\GrammarHelper;
use Bernskiold\LaravelSnowflake\Values\Variant;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use RuntimeException;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_null;

class QueryGrammar extends Grammar
{
    use GrammarHelper;

    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like',
        'ilike', 'not ilike',
        'rlike', 'not rlike',
        'regexp', 'not regexp',
    ];

    /**
     * Determine if this connection should compile LIKE as ILIKE by default.
     */
    protected function shouldUseIlike(): bool
    {
        $value = $this->connection->getConfig('options.use_ilike');

        return (bool) ($value ?? config('snowflake.use_ilike', true));
    }

    /**
     * Compile a basic where clause.
     *
     * Snowflake supports both LIKE (case-sensitive) and ILIKE (case-insensitive).
     * This package defaults to ILIKE for broader compatibility with typical
     * Laravel expectations (case-insensitive search), but can be disabled via:
     * `options.use_ilike = false`.
     *
     * @param  array  $where
     */
    protected function whereBasic(Builder $query, $where)
    {
        $operator = strtolower($where['operator']);

        if ($this->shouldUseIlike()) {
            if ($operator === 'like') {
                $where['operator'] = 'ilike';
            } elseif ($operator === 'not like') {
                $where['operator'] = 'not ilike';
            }
        }

        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return $this->wrap($where['column']).' '.$operator.' '.$value;
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * Values wrapped in a Variant are written through PARSE_JSON so Snowflake
     * parses the JSON payload into a semi-structured (VARIANT/OBJECT/ARRAY)
     * value. The value still flows as a real bound parameter; only the
     * placeholder changes.
     *
     * Snowflake rejects function expressions such as PARSE_JSON() inside a
     * VALUES clause, so inserts and upserts deliberately do not route their row
     * values through this method — see compileInsert()/compileUpsert(), which
     * bind raw placeholders and apply PARSE_JSON in a SELECT projection instead.
     * This method still drives the valid contexts (where clauses and update
     * assignments).
     *
     * @param  mixed  $value
     * @return string
     */
    public function parameter($value)
    {
        if ($value instanceof Variant) {
            return 'PARSE_JSON(?)';
        }

        return parent::parameter($value);
    }

    /**
     * Compile an insert statement into SQL.
     *
     * Snowflake forbids function expressions such as PARSE_JSON() inside a
     * VALUES clause, so when any value is a Variant we cannot use the standard
     * `insert into t (...) values (?, PARSE_JSON(?))` form. Instead we bind the
     * raw values into a VALUES table constructor and apply PARSE_JSON in the
     * SELECT projection that reads from it:
     *
     *   insert into t (code, name) select column1, parse_json(column2) from values (?, ?)
     *
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        if (empty($values)) {
            return parent::compileInsert($query, $values);
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        if (! $this->valuesContainVariant($values)) {
            return parent::compileInsert($query, $values);
        }

        $table = $this->wrapTable($query->from);

        $columns = array_keys(reset($values));

        $projection = collect($columns)
            ->map(function ($column, $index) use ($values) {
                $position = 'column'.($index + 1);

                return $this->columnContainsVariant($values, $column)
                    ? 'parse_json('.$position.')'
                    : $position;
            })
            ->implode(', ');

        $rows = collect($values)
            ->map(fn ($record) => '('.$this->parameterizeWithoutVariant($record).')')
            ->implode(', ');

        return "insert into {$table} ({$this->columnize($columns)}) select {$projection} from values {$rows}";
    }

    /**
     * Determine if any row carries a Variant value.
     */
    protected function valuesContainVariant(array $values): bool
    {
        foreach ($values as $record) {
            foreach ($record as $value) {
                if ($value instanceof Variant) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine if a given column carries a Variant value in any row.
     */
    protected function columnContainsVariant(array $values, string $column): bool
    {
        foreach ($values as $record) {
            if (($record[$column] ?? null) instanceof Variant) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parameterize a record using raw placeholders, leaving Variant values as a
     * plain `?` so PARSE_JSON can be applied in the surrounding SELECT
     * projection rather than inside the (unsupported) VALUES clause.
     */
    protected function parameterizeWithoutVariant(array $values): string
    {
        return collect($values)
            ->map(fn ($value) => $value instanceof Variant ? '?' : $this->parameter($value))
            ->implode(', ');
    }

    /**
     * Compile a "where like" clause.
     *
     * @param  array  $where
     */
    protected function whereLike(Builder $query, $where)
    {
        $caseSensitive = (bool) ($where['caseSensitive'] ?? false);
        $operator = $caseSensitive ? 'like' : 'ilike';

        if (($where['not'] ?? false) === true) {
            $operator = 'not '.$operator;
        }

        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']).' '.$operator.' '.$value;
    }

    /**
     * Compile an update statement into SQL.
     *
     * @return string
     */
    public function compileUpdate(Builder $query, array $values)
    {
        if (isset($query->limit)) {
            throw new RuntimeException('Snowflake does not support update statements with a limit.');
        }

        return parent::compileUpdate($query, $values);
    }

    /**
     * Compile an update statement with joins into SQL.
     *
     * @param  string  $table
     * @param  string  $columns
     * @param  string  $where
     * @return string
     */
    protected function compileUpdateWithJoins(Builder $query, $table, $columns, $where)
    {
        throw new RuntimeException('Snowflake does not support update statements with joins. Use a merge statement instead.');
    }

    /**
     * Compile the columns for an update statement.
     *
     * @return string
     */
    protected function compileUpdateColumns(Builder $query, array $values)
    {
        foreach (array_keys($values) as $key) {
            if ($this->isJsonSelector($key)) {
                throw new RuntimeException('Snowflake does not support updating individual JSON keys. Update the entire column instead.');
            }
        }

        return parent::compileUpdateColumns($query, $values);
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        if (isset($query->limit)) {
            throw new RuntimeException('Snowflake does not support delete statements with a limit.');
        }

        return parent::compileDelete($query);
    }

    /**
     * Compile a delete statement with joins into SQL.
     *
     * @param  string  $table
     * @param  string  $where
     * @return string
     */
    protected function compileDeleteWithJoins(Builder $query, $table, $where)
    {
        throw new RuntimeException('Snowflake does not support delete statements with joins. Use a merge statement instead.');
    }

    /**
     * Compile an insert ignore statement into SQL.
     *
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        throw new RuntimeException('Snowflake does not support insert or ignore. Use upsert() instead.');
    }

    /**
     * Compile an insert ignore statement using a subquery into SQL.
     *
     * @return string
     */
    public function compileInsertOrIgnoreUsing(Builder $query, array $columns, string $sql)
    {
        throw new RuntimeException('Snowflake does not support insert or ignore. Use upsert() instead.');
    }

    /**
     * Compile an "upsert" statement into SQL using Snowflake's MERGE.
     *
     * @return string
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        $table = $this->wrapTable($query->from);

        $columns = array_keys(reset($values));

        // Expose the bound values as a derived table. Snowflake names the
        // columns of a VALUES clause column1..columnN, so alias them back to
        // the real column names. Variant columns are parsed here in the SELECT
        // projection because Snowflake forbids PARSE_JSON() inside the VALUES
        // clause itself.
        $source = collect($columns)
            ->map(function ($column, $index) use ($values) {
                $position = 'column'.($index + 1);

                $expression = $this->columnContainsVariant($values, $column)
                    ? 'parse_json('.$position.')'
                    : $position;

                return $expression.' as '.$this->wrap($column);
            })
            ->implode(', ');

        $rows = collect($values)
            ->map(fn ($record) => '('.$this->parameterizeWithoutVariant($record).')')
            ->implode(', ');

        $on = collect($uniqueBy)
            ->map(fn ($column) => $table.'.'.$this->wrap($column).' = laravel_source.'.$this->wrap($column))
            ->implode(' and ');

        $update = collect($update)
            ->map(function ($value, $key) {
                return is_numeric($key)
                    ? $this->wrap($value).' = laravel_source.'.$this->wrap($value)
                    : $this->wrap($key).' = '.$this->parameter($value);
            })
            ->implode(', ');

        $insertColumns = $this->columnize($columns);

        $insertValues = collect($columns)
            ->map(fn ($column) => 'laravel_source.'.$this->wrap($column))
            ->implode(', ');

        $sql = "merge into {$table} using (select {$source} from values {$rows}) as laravel_source on {$on}";

        if ($update !== '') {
            $sql .= " when matched then update set {$update}";
        }

        return $sql." when not matched then insert ({$insertColumns}) values ({$insertValues})";
    }

    /**
     * Compile an exists statement into SQL.
     *
     * Snowflake does not allow EXISTS() in a select list, so count the rows
     * of the wrapped query instead.
     *
     * @return string
     */
    public function compileExists(Builder $query)
    {
        return 'select count(*) as "exists" from ('.$this->compileSelect($query).') as laravel_exists';
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        // If the query has a "distinct" constraint and we're not asking for all columns
        // we need to prepend "distinct" onto the column name so that the query takes
        // it into account when it performs the aggregating operations on the data.
        if (is_array($query->distinct)) {
            $column = 'distinct '.$this->columnize($query->distinct);
        } elseif ($query->distinct && $column !== '*') {
            $column = 'distinct '.$column;
        }

        // The alias is always double-quoted so the result set key is
        // "aggregate" regardless of the connection's casing mode.
        return 'select '.$aggregate['function'].'('.$column.') as "aggregate"';
    }

    /**
     * Compile the lock into SQL.
     *
     * Snowflake has no row-level locking.
     *
     * @param  bool|string  $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        return '';
    }

    /**
     * Determine if the grammar supports savepoints.
     *
     * Snowflake does not support savepoints (nested transactions).
     *
     * @return bool
     */
    public function supportsSavepoints()
    {
        return false;
    }

    /**
     * Wrap a union subquery in parentheses.
     *
     * @param  string  $sql
     * @return string
     */
    protected function wrapUnion($sql)
    {
        return 'select * from ('.$sql.')';
    }

    /**
     * Compile a "where date" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereDate(Builder $query, $where)
    {
        return $this->dateBasedWhere('%Y-%m-%d', $query, $where);
    }

    /**
     * Compile a "where day" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereDay(Builder $query, $where)
    {
        return $this->dateBasedWhere('%d', $query, $where);
    }

    /**
     * Compile a "where month" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereMonth(Builder $query, $where)
    {
        return $this->dateBasedWhere('%m', $query, $where);
    }

    /**
     * Compile a "where year" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereYear(Builder $query, $where)
    {
        return $this->dateBasedWhere('%Y', $query, $where);
    }

    /**
     * Compile a "where time" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereTime(Builder $query, $where)
    {
        return $this->dateBasedWhere('%H:%M:%S', $query, $where);
    }

    /**
     * Compile a "where fulltext" clause using Snowflake's SEARCH function.
     *
     * An analyzer can be chosen via the options array:
     * whereFullText('body', 'query', ['analyzer' => 'UNICODE_ANALYZER']).
     *
     * @see https://docs.snowflake.com/en/sql-reference/functions/search
     *
     * @param  array  $where
     * @return string
     */
    public function whereFullText(Builder $query, $where)
    {
        $columns = $this->columnize($where['columns']);

        if (count($where['columns']) > 1) {
            $columns = '('.$columns.')';
        }

        $value = $this->parameter($where['value']);

        if ($analyzer = $where['options']['analyzer'] ?? null) {
            return 'search('.$columns.', '.$value.", analyzer => '".str_replace("'", "''", $analyzer)."')";
        }

        return 'search('.$columns.', '.$value.')';
    }

    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  array  $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $column = $this->wrap($where['column']);
        $value = $this->parameter($where['value']);

        // For full date comparisons rely on a native date cast instead of string formatting
        if ($type === '%Y-%m-%d') {
            return "{$column} {$where['operator']} {$value}::DATE";
        }

        // Map generic formats to Snowflake TO_VARCHAR patterns
        $format = match ($type) {
            '%d' => 'DD',
            '%m' => 'MM',
            '%Y' => 'YYYY',
            '%H:%M:%S' => 'HH24:MI:SS',
            default => 'YYYY-MM-DD',
        };

        return "TO_VARCHAR({$column}, '{$format}') {$where['operator']} {$value}";
    }

    /**
     * Split the given JSON selector into the wrapped field and the quoted
     * GET_PATH path expression.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitJsonFieldAndPath(string $column): array
    {
        $parts = explode('->', $column);

        $field = $this->wrap(array_shift($parts));

        $path = implode('.', $parts);

        return [$field, "'".str_replace("'", "''", $path)."'"];
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapJsonSelector($value)
    {
        [$field, $path] = $this->splitJsonFieldAndPath($value);

        return 'get_path('.$field.', '.$path.')';
    }

    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $value
     * @return string
     */
    protected function compileJsonContains($column, $value)
    {
        $target = $this->isJsonSelector($column) ? $this->wrapJsonSelector($column) : $this->wrap($column);

        return 'array_contains(parse_json('.$value.'), '.$target.')';
    }

    /**
     * Prepare the binding for a "JSON contains" statement.
     *
     * @param  mixed  $binding
     * @return string
     */
    public function prepareBindingForJsonContains($binding)
    {
        return json_encode($binding);
    }

    /**
     * Compile a "JSON contains key" statement into SQL.
     *
     * @param  string  $column
     * @return string
     */
    protected function compileJsonContainsKey($column)
    {
        return $this->wrapJsonSelector($column).' is not null';
    }

    /**
     * Compile a "JSON length" statement into SQL.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     * @return string
     */
    protected function compileJsonLength($column, $operator, $value)
    {
        $target = $this->isJsonSelector($column) ? $this->wrapJsonSelector($column) : $this->wrap($column);

        return 'array_size('.$target.') '.$operator.' '.$value;
    }

    /**
     * Escape a value for safe SQL embedding.
     *
     * @param  string|float|int|bool|null  $value
     * @param  bool  $binary
     * @return string
     */
    public function escape($value, $binary = false)
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($binary) {
            return "to_binary('".bin2hex($value)."', 'hex')";
        }

        if (str_contains($value, "\00")) {
            throw new RuntimeException('Strings with null bytes cannot be escaped. Use the binary escape option instead.');
        }

        // Backslash is an escape character inside Snowflake string literals,
        // so it has to be escaped along with the quote itself.
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
    }
}
