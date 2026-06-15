<?php

namespace Bernskiold\LaravelSnowflake\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

/**
 * Changing actions:
 * - Add column
 * - Delete column
 * - Rename column
 * - Change column type
 *     - type change
 *     - precision change
 *     - null to not null
 *     - not null to null.
 */
class ChangeColumn
{
    /**
     * Compile a change column command into a series of SQL statements.
     *
     * @return array|string
     */
    public static function compile(SchemaGrammar $grammar, Blueprint $blueprint, Fluent $command)
    {
        $type = $command->offsetGet('name'); // can be: change, dropColumn, renameColumn

        if ($type === 'dropColumn') {
            return $grammar->compileDropColumn($blueprint, $command);
        } elseif ($type === 'renameColumn') {
            return $grammar->compileRenameColumn($blueprint, $command);
        }

        return $grammar->compileChangeColumn($blueprint, $command);
    }
}
