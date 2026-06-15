<?php

namespace Bernskiold\LaravelSnowflake;

use Bernskiold\LaravelSnowflake\Odbc\OdbcConnector;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\ServiceProvider;

class SnowflakeServiceProvider extends ServiceProvider
{
    /**
     * Register the Snowflake database drivers.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/snowflake.php', 'snowflake');

        Connection::resolverFor('snowflake', SnowflakeConnector::registerDriver());
        Connection::resolverFor('snowflake_native', SnowflakeConnector::registerDriver());
        Connection::resolverFor('odbc', OdbcConnector::registerDriver());
    }

    /**
     * Bootstrap the package services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/snowflake.php' => config_path('snowflake.php'),
            ], 'snowflake-config');
        }

        $this->registerTimeTravelMacros();
    }

    /**
     * Register query builder macros for Snowflake time travel.
     *
     * @see https://docs.snowflake.com/en/sql-reference/constructs/at-before
     */
    protected function registerTimeTravelMacros(): void
    {
        if (! QueryBuilder::hasMacro('atTimestamp')) {
            QueryBuilder::macro('atTimestamp', function (DateTimeInterface|string $timestamp) {
                /** @var QueryBuilder $this */
                $value = $timestamp instanceof DateTimeInterface
                    ? $timestamp->format('Y-m-d H:i:s.uP')
                    : $timestamp;

                $table = $this->grammar->wrapTable($this->from);

                return $this->fromRaw(
                    $table." at (timestamp => '".str_replace("'", "''", $value)."'::timestamp_tz)"
                );
            });
        }

        if (! QueryBuilder::hasMacro('beforeStatement')) {
            QueryBuilder::macro('beforeStatement', function (string $statementId) {
                /** @var QueryBuilder $this */
                $table = $this->grammar->wrapTable($this->from);

                return $this->fromRaw(
                    $table." before (statement => '".str_replace("'", "''", $statementId)."')"
                );
            });
        }
    }
}
