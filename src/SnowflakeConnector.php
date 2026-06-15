<?php

namespace Bernskiold\LaravelSnowflake;

use Bernskiold\LaravelSnowflake\Contracts\OdbcDriver;
use Bernskiold\LaravelSnowflake\Odbc\OdbcConnector;
use Bernskiold\LaravelSnowflake\PDO\Statement;
use Closure;
use Exception;
use Illuminate\Support\Arr;
use PDO;

/**
 * Snowflake Connector
 * Inspiration: https://github.com/jenssegers/laravel-mongodb.
 */
class SnowflakeConnector extends OdbcConnector implements OdbcDriver
{
    /**
     * Establish a database connection.
     *
     * @return PDO
     *
     * @internal param array $options
     */
    public function connect(array $config)
    {
        $usingSnowflakeDriver = $config['driver'] === 'snowflake_native';
        $temporaryKeyFile = null;

        if (Arr::get($config, 'authenticator') === 'key_pair') {
            [$config, $temporaryKeyFile] = $this->configureKeyPairAuth($config, $usingSnowflakeDriver);
        }

        // the PDO Snowflake driver was installed and the driver was snowflake, start using snowflake driver.
        if ($usingSnowflakeDriver) {
            $this->dsnPrefix = 'snowflake';
            $this->dsnIncludeDriver = false;

            if (! extension_loaded('pdo_snowflake')) {
                throw new Exception('Native Snowflake driver pdo_snowflake was not enabled');
            }
        }

        try {
            $connection = parent::connect($config);
        } finally {
            // The driver reads the key during the auth handshake, so the file is
            // no longer needed once the connection is established (or has failed).
            if ($temporaryKeyFile !== null && is_file($temporaryKeyFile)) {
                @unlink($temporaryKeyFile);
            }
        }

        // The Snowflake ODBC driver cannot stream bind values, so a custom
        // statement class interpolates them instead. The native pdo_snowflake
        // driver binds parameters properly and keeps the default statement.
        if (! $usingSnowflakeDriver) {
            $connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, [$connection]]);
        }

        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $connection;
    }

    /**
     * Resolve the key-pair private key to a file path and rewrite the config so
     * the DSN carries the parameters the active driver understands.
     *
     * Both the native pdo_snowflake driver and the Snowflake ODBC driver take a
     * path to a PEM-encoded private key; they differ only in DSN key casing, and
     * DSN keys are matched case-sensitively. A key supplied inline via the
     * "private_key" option is written to a secured temp file whose path is
     * returned so the caller can remove it once the connection is established.
     *
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    protected function configureKeyPairAuth(array $config, bool $usingSnowflakeDriver): array
    {
        $config['authenticator'] = 'SNOWFLAKE_JWT';
        $config['odbc_driver'] = Arr::get($config, 'odbcdriver');

        $keyFile = trim((string) Arr::get($config, 'private_key_path', ''));
        $privateKey = trim((string) Arr::get($config, 'private_key', ''));
        $temporaryKeyFile = null;

        if ($keyFile !== '') {
            if (! is_readable($keyFile)) {
                throw new Exception("The configured Snowflake private key file [{$keyFile}] does not exist or is not readable.");
            }
        } elseif ($privateKey !== '') {
            $keyFile = $temporaryKeyFile = $this->writeTemporaryKeyFile($privateKey);
        } else {
            throw new Exception('A private key is required for key_pair authentication. Set the "private_key_path" or "private_key" connection option.');
        }

        $fileKey = $usingSnowflakeDriver ? 'priv_key_file' : 'PRIV_KEY_FILE';
        $pwdKey = $usingSnowflakeDriver ? 'priv_key_file_pwd' : 'PRIV_KEY_FILE_PWD';

        $config[$fileKey] = $keyFile;

        $passphrase = Arr::get($config, 'private_key_passphrase', Arr::get($config, 'priv_key_pwd'));
        if ($passphrase !== null && $passphrase !== '') {
            $config[$pwdKey] = $passphrase;
        }

        $allowedKeys = ['driver', 'account', 'server', 'database', 'warehouse', 'schema', 'port', $pwdKey, 'odbc_driver', 'authenticator', $fileKey, 'odbcdriver', 'username', 'options'];

        $config = array_intersect_key($config, array_flip($allowedKeys));

        return [$config, $temporaryKeyFile];
    }

    /**
     * Write an inline private key to a temporary, owner-only readable file.
     */
    protected function writeTemporaryKeyFile(string $privateKey): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'snowflake_key_');

        if ($tempFile === false) {
            throw new Exception('Unable to create a temporary file for the Snowflake private key.');
        }

        file_put_contents($tempFile, $privateKey);
        chmod($tempFile, 0600);

        return $tempFile;
    }

    /**
     * Register the connection driver into the DatabaseManager.
     */
    public static function registerDriver(): Closure
    {
        return function ($connection, $database, $prefix, $config) {
            $connection = (new self)->connect($config);

            // create connection
            $db = new SnowflakeConnection($connection, $database, $prefix, $config);

            // Keep quoted identifiers case-sensitive so the grammar's quoting
            // semantics hold, unless explicitly disabled per connection or
            // through the package configuration.
            $forceQuoted = Arr::get($config, 'options.force_quoted_identifiers')
                ?? config('snowflake.force_quoted_identifiers', true);

            if ($forceQuoted) {
                $connection->exec('ALTER SESSION SET QUOTED_IDENTIFIERS_IGNORE_CASE = false');
            }

            // set default fetch mode for PDO
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $db->getFetchMode());

            return $db;
        };
    }
}
