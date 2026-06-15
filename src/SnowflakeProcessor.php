<?php

namespace Bernskiold\LaravelSnowflake;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

use function in_array;

class SnowflakeProcessor extends BaseProcessor
{
    /**
     * Process the results of a columns query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumns($results)
    {
        return array_map(function ($result) {
            $result = (object) $result;

            $type = strtolower($result->type_name ?? $result->type);

            if (in_array($type, ['varchar', 'char', 'character', 'string', 'text'], true) && ! empty($result->char_length)) {
                $fullType = $type.'('.$result->char_length.')';
            } elseif (in_array($type, ['number', 'numeric', 'decimal'], true) && isset($result->numeric_precision)) {
                $fullType = $type.'('.(int) $result->numeric_precision.','.(int) $result->numeric_scale.')';
            } else {
                $fullType = $type;
            }

            return [
                'name' => $result->name,
                'type_name' => $type,
                'type' => $fullType,
                'collation' => $result->collation ?? null,
                'nullable' => strtoupper((string) ($result->nullable ?? '')) === 'YES',
                'default' => $result->default ?? null,
                'auto_increment' => strtoupper((string) ($result->auto_increment ?? '')) === 'YES',
                'comment' => $result->comment ?? null,
                'generation' => null,
            ];
        }, $results);
    }

    /**
     * Process an "insert get ID" query.
     *
     * Snowflake's PDO drivers do not support lastInsertId, so the maximum
     * value of the key column is selected after the insert. The alias is
     * double-quoted so the result key matches the requested column name
     * regardless of the connection's casing mode.
     *
     * WARNING: this is inherently racy — under concurrent writers the
     * returned id may belong to another session's insert. Prefer
     * client-generated keys (UUIDs/ULIDs) for tables written to by
     * concurrent processes.
     *
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int|string
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $connection = $query->getConnection();

        $connection->insert($sql, $values);

        $grammar = $query->getGrammar();
        $idColumn = $sequence ?: 'id';
        $alias = '"'.str_replace('"', '""', $idColumn).'"';

        $result = $connection->selectOne(sprintf(
            'select max(%s) as %s from %s',
            $grammar->wrap($idColumn),
            $alias,
            $grammar->wrapTable($query->from)
        ));

        $id = $result->{$idColumn};

        return is_numeric($id) ? (int) $id : $id;
    }
}
