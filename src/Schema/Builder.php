<?php

namespace Bernskiold\LaravelSnowflake\Schema;

use Bernskiold\LaravelSnowflake\Grammars\SchemaGrammar;
use Illuminate\Database\Schema\Builder as BaseBuilder;

use function count;

class Builder extends BaseBuilder
{
    /**
     * The schema grammar instance. Custom grammars configured through the
     * "options.grammar.schema" connection option must extend SchemaGrammar.
     *
     * @var SchemaGrammar
     */
    protected $grammar;

    /**
     * Determine if the given table exists.
     *
     * @param  string  $table
     * @return bool
     */
    public function hasTable($table)
    {
        $table = $this->grammar->caseFoldName($this->connection->getTablePrefix().$table);

        $schema = $this->connection->getConfig('schema');

        return count($this->connection->selectFromWriteConnection(
            $this->grammar->compileTableExists(
                $this->connection->getDatabaseName(),
                $table,
                $schema ? $this->grammar->caseFoldName($schema) : null
            )
        )) > 0;
    }

    /**
     * Drop all tables from the database.
     *
     * Snowflake drops one table per statement and has no (enforceable)
     * foreign key checks to toggle.
     *
     * @return void
     */
    public function dropAllTables()
    {
        foreach ($this->getAllTables() as $table) {
            $this->connection->statement(
                'drop table if exists '.$this->grammar->wrapTable($table).' cascade'
            );
        }
    }

    /**
     * Drop all views from the database.
     *
     * @return void
     */
    public function dropAllViews()
    {
        foreach ($this->getAllViews() as $view) {
            $this->connection->statement(
                'drop view if exists '.$this->grammar->wrapTable($view)
            );
        }
    }

    /**
     * Get all of the table names for the current schema.
     *
     * @return array
     */
    public function getAllTables()
    {
        $tables = $this->connection->select($this->grammar->compileGetAllTables());

        return array_column(array_map(fn ($table) => (array) $table, $tables), 'name');
    }

    /**
     * Get all of the view names for the current schema.
     *
     * @return array
     */
    public function getAllViews()
    {
        $views = $this->connection->select($this->grammar->compileGetAllViews());

        return array_column(array_map(fn ($view) => (array) $view, $views), 'name');
    }

    /**
     * Create a database in the schema.
     *
     * @param  string  $name
     * @return bool
     */
    public function createDatabase($name)
    {
        return $this->connection->statement(
            $this->grammar->compileCreateDatabase($name)
        );
    }

    /**
     * Drop a database from the schema.
     *
     * @param  string  $name
     * @return bool
     */
    public function dropDatabase($name)
    {
        return $this->connection->statement(
            $this->grammar->compileDropDatabase($name)
        );
    }

    /**
     * Drop a database from the schema if the database exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function dropDatabaseIfExists($name)
    {
        return $this->connection->statement(
            $this->grammar->compileDropDatabaseIfExists($name)
        );
    }
}
