<?php

namespace Bernskiold\LaravelSnowflake\Grammars;

use const FILTER_VALIDATE_BOOLEAN;

use Bernskiold\LaravelSnowflake\Concerns\GrammarHelper;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Fluent;
use RuntimeException;

use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_null;

/**
 * Schema grammar for Snowflake.
 *
 * Data types: https://docs.snowflake.com/en/sql-reference/intro-summary-data-types
 * Semi-structured types: https://docs.snowflake.com/en/sql-reference/data-types-semistructured
 * Column alteration rules: https://docs.snowflake.com/en/sql-reference/sql/alter-table-column
 */
class SchemaGrammar extends BaseGrammar
{
    use GrammarHelper;

    /**
     * The possible column modifiers, in the order Snowflake documents the
     * column clauses: collate, comment, default/autoincrement, not null,
     * inline constraint.
     *
     * @var string[]
     */
    protected $modifiers = [
        'VirtualAs', 'StoredAs', 'Collate', 'Comment',
        'Default', 'Increment', 'Nullable', 'PrimaryKey',
    ];

    /**
     * The possible column serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile the query to determine if the given table exists.
     *
     * @param  string  $database
     * @param  string  $table
     * @param  string|null  $schema
     * @return string
     */
    public function compileTableExists($database, $table, $schema = null)
    {
        $sql = sprintf(
            "select * from %s.information_schema.tables where table_name = %s and table_type = 'BASE TABLE'",
            $this->wrap($database),
            $this->quoteStringLiteral($table)
        );

        if ($schema) {
            $sql .= ' and table_schema = '.$this->quoteStringLiteral($schema);
        }

        return $sql;
    }

    /**
     * Compile the query to determine the tables.
     *
     * @param  string|string[]|null  $schema
     * @return string
     */
    public function compileTables($schema)
    {
        return sprintf(
            'select table_name as "name", table_schema as "schema", bytes as "size", comment as "comment" '
                ."from %s.information_schema.tables where table_type = 'BASE TABLE'%s order by table_name",
            $this->wrap($this->connection->getDatabaseName()),
            $this->compileSchemaFilter($schema)
        );
    }

    /**
     * Compile the query to determine the views.
     *
     * @param  string|string[]|null  $schema
     * @return string
     */
    public function compileViews($schema)
    {
        return sprintf(
            'select table_name as "name", table_schema as "schema", view_definition as "definition" '
                .'from %s.information_schema.views%s order by table_name',
            $this->wrap($this->connection->getDatabaseName()),
            ' where 1 = 1'.$this->compileSchemaFilter($schema)
        );
    }

    /**
     * Compile the query to determine the columns of a table.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileColumns($schema, $table)
    {
        $sql = sprintf(
            'select column_name as "name", data_type as "type_name", data_type as "type", '
                .'character_maximum_length as "char_length", numeric_precision as "numeric_precision", numeric_scale as "numeric_scale", '
                .'is_nullable as "nullable", column_default as "default", is_identity as "auto_increment", '
                .'collation_name as "collation", comment as "comment" '
                .'from %s.information_schema.columns where table_name = %s',
            $this->wrap($this->connection->getDatabaseName()),
            $this->quoteStringLiteral($this->caseFoldName($table))
        );

        if ($schema = $schema ?: $this->connection->getConfig('schema')) {
            $sql .= ' and table_schema = '.$this->quoteStringLiteral($this->caseFoldName($schema));
        }

        return $sql.' order by ordinal_position';
    }

    /**
     * Compile the query to determine the indexes of a table.
     *
     * Snowflake does not have indexes, so this yields an empty result set.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileIndexes($schema, $table)
    {
        return 'select \'\' as "name", \'\' as "columns", \'\' as "type", false as "unique", false as "primary" limit 0';
    }

    /**
     * Compile the query to determine the foreign keys of a table.
     *
     * Snowflake stores foreign keys as unenforced, informational constraints
     * whose columns are not exposed through information_schema, so this
     * yields an empty result set.
     *
     * @param  string|null  $schema
     * @param  string  $table
     * @return string
     */
    public function compileForeignKeys($schema, $table)
    {
        return 'select \'\' as "name", \'\' as "columns", \'\' as "foreign_schema", \'\' as "foreign_table", '
            .'\'\' as "foreign_columns", null as "on_update", null as "on_delete" limit 0';
    }

    /**
     * Compile a schema filter clause for information_schema queries.
     *
     * @param  string|string[]|null  $schema
     */
    protected function compileSchemaFilter($schema): string
    {
        $schemas = array_filter(array_map(strval(...), (array) ($schema ?: $this->connection->getConfig('schema'))));

        if (count($schemas) === 0) {
            return " and table_schema != 'INFORMATION_SCHEMA'";
        }

        $list = implode(', ', array_map(
            fn ($name) => $this->quoteStringLiteral($this->caseFoldName($name)),
            $schemas
        ));

        return ' and table_schema in ('.$list.')';
    }

    /**
     * Compile a create table command.
     *
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        return trim(sprintf(
            '%s table %s (%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        ));
    }

    /**
     * Compile an add column command.
     *
     * @return array
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $prefix = 'alter table '.$this->wrapTable($blueprint).' add column';

        // Laravel dispatches one "add" command per column. Only compile all
        // added columns when the command does not carry a single column.
        $columns = $command->column
            ? $this->handleNullables([$this->getColumn($blueprint, $command->column)], false)
            : $this->getColumns($blueprint);

        return $this->prefixArray($prefix, $columns);
    }

    /**
     * Compile a change column command.
     *
     * @return array
     */
    public function compileChangeColumn(Blueprint $blueprint, Fluent $command)
    {
        $prefix = sprintf('alter table %s modify column', $this->wrapTable($blueprint));

        // Laravel dispatches one "change" command per column. Only compile all
        // changed columns when the command does not carry a single column.
        $changed = $command->column ? [$command->column] : $blueprint->getChangedColumns();

        $columns = $this->handleNullables(
            array_map(fn ($column) => $this->getColumn($blueprint, $column), $changed),
            true
        );

        $columns = $this->prefixArray($prefix, $columns);

        return array_values(array_merge(
            $columns,
            (array) $this->compileAutoIncrementStartingValues($blueprint, $command)
        ));
    }

    /**
     * Compile the auto incrementing column starting values.
     *
     * @return string|null
     */
    public function compileAutoIncrementStartingValues(Blueprint $blueprint, Fluent $command)
    {
        if (! $command->column || ! $command->column->autoIncrement) {
            return null;
        }

        if ($value = $command->column->get('startingValue', $command->column->get('from'))) {
            return 'alter table '.$this->wrapTable($blueprint).' autoincrement start '.$value;
        }

        return null;
    }

    /**
     * Compile a primary key command.
     *
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'primary key');
    }

    /**
     * Compile a unique key command.
     *
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'unique');
    }

    /**
     * Compile a plain index key command.
     *
     * Snowflake does not support indexes; the command is silently skipped so
     * migrations written for other databases keep working.
     *
     * @return null
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }

    /**
     * Compile a spatial index key command.
     *
     * @return null
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }

    /**
     * Compile a fulltext index key command.
     *
     * @return null
     */
    public function compileFulltext(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }

    /**
     * Compile a drop table command.
     *
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop database (if exists) command.
     *
     * @return string
     */
    public function compileDropDatabaseIfExists($name)
    {
        return 'drop database if exists '.$this->wrap($name);
    }

    /**
     * Compile a drop database command.
     *
     * @return string
     */
    public function compileDropDatabase($name)
    {
        return 'drop database '.$this->wrap($name);
    }

    /**
     * Compile a create database command.
     *
     * @param  string  $name
     * @return string
     */
    public function compileCreateDatabase($name)
    {
        return 'create database '.$this->wrap($name);
    }

    /**
     * Compile a drop column command.
     *
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->prefixArray('drop column', $this->wrapArray($command->columns));

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
    }

    /**
     * Compile a drop primary key command.
     *
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop primary key';
    }

    /**
     * Compile a drop unique key command.
     *
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a drop index command.
     *
     * Snowflake does not support indexes; the command is silently skipped.
     *
     * @return null
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }

    /**
     * Compile a drop spatial index command.
     *
     * @return null
     */
    public function compileDropSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }

    /**
     * Compile a drop fulltext index command.
     *
     * @return null
     */
    public function compileDropFullText(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }

    /**
     * Compile a drop foreign key command.
     *
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->index)
        );
    }

    /**
     * Compile a rename table command.
     *
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s rename to %s',
            $this->wrapTable($blueprint),
            $this->wrapTable($command->to)
        );
    }

    /**
     * Compile a rename index command.
     *
     * Snowflake does not support indexes; the command is silently skipped.
     *
     * @return null
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }

    /**
     * Compile the SQL needed to retrieve all table names.
     *
     * @return string
     */
    public function compileGetAllTables()
    {
        return 'SHOW TABLES';
    }

    /**
     * Compile the SQL needed to retrieve all view names.
     *
     * @return string
     */
    public function compileGetAllViews()
    {
        return 'SHOW VIEWS';
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * Snowflake foreign keys are informational and never enforced, so this is
     * a harmless no-op statement.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints()
    {
        return 'select 1';
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints()
    {
        return 'select 1';
    }

    /**
     * Compile a change column command into a series of SQL statements.
     *
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function compileChange(Blueprint $blueprint, Fluent $command)
    {
        return ChangeColumn::compile($this, $blueprint, $command);
    }

    /**
     * Compile an index creation command.
     *
     * @param  string  $type
     * @return string
     */
    protected function compileKey(Blueprint $blueprint, Fluent $command, $type)
    {
        return sprintf(
            'alter table %s add constraint %s %s (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $type,
            $this->columnize($command->columns)
        );
    }

    /**
     * Create the column definition for a char type.
     *
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return $column->length ? "char({$column->length})" : 'char';
    }

    /**
     * Create the column definition for a string type.
     *
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return $column->length ? "varchar({$column->length})" : 'varchar';
    }

    /**
     * Create the column definition for a tiny text type.
     *
     * @return string
     */
    protected function typeTinyText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a text type.
     *
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a big integer type.
     *
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'bigint';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        return 'int';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'int';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a float type.
     *
     * Snowflake floats do not take precision arguments.
     *
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        return 'float';
    }

    /**
     * Create the column definition for a double type.
     *
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        return 'double';
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'boolean';
    }

    /**
     * Create the column definition for an enumeration type.
     *
     * Snowflake has no enum type and does not enforce check constraints, so
     * enums are stored as plain varchars.
     *
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return 'varchar';
    }

    /**
     * Create the column definition for a set enumeration type.
     *
     * @return string
     */
    protected function typeSet(Fluent $column)
    {
        return 'varchar';
    }

    /**
     * Create the column definition for a json type.
     *
     * @return string
     */
    protected function typeJson(Fluent $column)
    {
        return 'variant';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @return string
     */
    protected function typeJsonb(Fluent $column)
    {
        return 'variant';
    }

    /**
     * Create the column definition for a date type.
     *
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        $columnType = $column->precision ? "timestamp_ntz($column->precision)" : 'timestamp_ntz';

        return $column->useCurrent ? "$columnType default CURRENT_TIMESTAMP" : $columnType;
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     *
     * @return string
     */
    protected function typeDateTimeTz(Fluent $column)
    {
        $columnType = $column->precision ? "timestamp_tz($column->precision)" : 'timestamp_tz';

        return $column->useCurrent ? "$columnType default CURRENT_TIMESTAMP" : $columnType;
    }

    /**
     * Create the column definition for a time type.
     *
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return $column->precision ? "time($column->precision)" : 'time';
    }

    /**
     * Create the column definition for a time (with time zone) type.
     *
     * Snowflake's TIME type has no time zone variant.
     *
     * @return string
     */
    protected function typeTimeTz(Fluent $column)
    {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * The MySQL-specific "on update CURRENT_TIMESTAMP" modifier is not
     * supported by Snowflake and is ignored.
     *
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        $columnType = $column->precision ? "timestamp_ntz($column->precision)" : 'timestamp_ntz';

        $current = $column->precision ? "CURRENT_TIMESTAMP($column->precision)" : 'CURRENT_TIMESTAMP';

        return $column->useCurrent ? "$columnType default $current" : $columnType;
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @return string
     */
    protected function typeTimestampTz(Fluent $column)
    {
        $columnType = $column->precision ? "timestamp_tz($column->precision)" : 'timestamp_tz';

        $current = $column->precision ? "CURRENT_TIMESTAMP($column->precision)" : 'CURRENT_TIMESTAMP';

        return $column->useCurrent ? "$columnType default $current" : $columnType;
    }

    /**
     * Create the column definition for a year type.
     *
     * @return string
     */
    protected function typeYear(Fluent $column)
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return $column->length ? "binary({$column->length})" : 'binary';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @return string
     */
    protected function typeUuid(Fluent $column)
    {
        return 'char(36)';
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @return string
     */
    protected function typeIpAddress(Fluent $column)
    {
        return 'varchar(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @return string
     */
    protected function typeMacAddress(Fluent $column)
    {
        return 'varchar(17)';
    }

    /**
     * Create the column definition for a spatial Geometry type.
     *
     * @return string
     */
    protected function typeGeometry(Fluent $column)
    {
        return 'geometry';
    }

    /**
     * Create the column definition for a spatial Geography type.
     *
     * @return string
     */
    protected function typeGeography(Fluent $column)
    {
        return 'geography';
    }

    /**
     * Create the column definition for a generated, computed column type.
     *
     *
     * @return void
     *
     * @throws RuntimeException
     */
    protected function typeComputed(Fluent $column)
    {
        throw new RuntimeException('This database driver requires a type, see the virtualAs / storedAs modifiers.');
    }

    /**
     * Get the SQL for a generated virtual column modifier.
     *
     * @return string|null
     */
    protected function modifyVirtualAs(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->virtualAs)) {
            return " as ({$column->virtualAs})";
        }

        return null;
    }

    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @return string|null
     */
    protected function modifyStoredAs(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->storedAs)) {
            return " as ({$column->storedAs})";
        }

        return null;
    }

    /**
     * Get the SQL for a collation column modifier.
     *
     * @return string|null
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->collation)) {
            return " collate '{$column->collation}'";
        }

        return null;
    }

    /**
     * Get the SQL for a "comment" column modifier.
     *
     * @return string|null
     */
    protected function modifyComment(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->comment)) {
            return ' comment '.$this->quoteStringLiteral($column->comment);
        }

        return null;
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @return string|null
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default, $column->type);
        }

        return null;
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials, true) && $column->autoIncrement) {
            return ' autoincrement';
        }

        return null;
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @return string|null
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        if (is_null($column->virtualAs) && is_null($column->storedAs)) {
            return $column->nullable ? ' null' : ' not null';
        }

        if ($column->nullable === false) {
            return ' not null';
        }

        return null;
    }

    /**
     * Get the SQL for the inline primary key of an auto-increment column.
     *
     * Inline constraints must be the last clause of a Snowflake column
     * definition, after NOT NULL.
     *
     * @return string|null
     */
    protected function modifyPrimaryKey(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials, true) && $column->autoIncrement) {
            return ' primary key';
        }

        return null;
    }

    /**
     * Compile the blueprint's column definitions.
     *
     * @return array
     */
    protected function getColumns(Blueprint $blueprint)
    {
        return $this->handleNullables(parent::getColumns($blueprint), false);
    }

    /**
     * Handle NULL or NOT NULL statements from within the queries.
     * Make separate queries and push them into the columns array.
     */
    protected function handleNullables(array $columns, bool $isChanging = false): array
    {
        foreach ($columns as $i => $column) {
            // on adding columns to the table
            if (! $isChanging) {
                if (! str_contains($column, ' not null') && str_contains($column, ' null')) {
                    $column = str_replace(' null', '', $column);
                }
            }
            // when changing the table
            else {
                // handle nullables
                if (str_contains($column, ' not null')) {
                    // query: "column" set not null
                    preg_match('/(\".+\"\s)/', $column, $match);
                    if (count($match) === 0) {
                        $match = explode(' ', $column);
                    }

                    $columns[] = trim($match[0]).' set not null';
                    $column = str_replace(' not null', '', $column);
                } elseif (str_contains($column, ' null')) {
                    // query: "column" drop not null
                    preg_match('/(\".+\"\s)/', $column, $match);
                    if (count($match) === 0) {
                        $match = explode(' ', $column);
                    }

                    $columns[] = trim($match[0]).' drop not null';
                    $column = str_replace(' null', '', $column);
                }

                // handle defaults, only sequence changes
                if (str_contains($column, 'default') && ! str_contains($column, '.nextval')) {
                    $split = explode('default', $column);
                    $column = reset($split);
                }
            }

            $columns[$i] = trim($column);
        }

        return $columns;
    }

    /**
     * Format a value so that it can be used in "default" clauses.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function getDefaultValue($value, $type = null)
    {
        if ($value instanceof Expression) {
            return $this->getValue($value);
        }

        if ($type === 'boolean' || is_bool($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE';
        }

        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return (string) $value;
        }

        return $this->quoteStringLiteral((string) $value);
    }

    /**
     * Quote a string literal for safe SQL embedding.
     */
    protected function quoteStringLiteral(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
    }
}
