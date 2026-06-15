<?php

use Bernskiold\LaravelSnowflake\SnowflakeConnector;

function configureKeyPairAuth(array $config, bool $usingSnowflakeDriver = false): array
{
    $connector = new SnowflakeConnector;
    $method = new ReflectionMethod($connector, 'configureKeyPairAuth');

    return $method->invoke($connector, $config, $usingSnowflakeDriver);
}

it('requires a private key for key pair authentication', function () {
    configureKeyPairAuth([
        'driver' => 'snowflake',
        'authenticator' => 'key_pair',
    ]);
})->throws(Exception::class, 'A private key is required for key_pair authentication');

it('rejects an unreadable private key file', function () {
    configureKeyPairAuth([
        'driver' => 'snowflake',
        'authenticator' => 'key_pair',
        'private_key_path' => '/nonexistent/rsa_key.p8',
    ]);
})->throws(Exception::class, 'does not exist or is not readable');

it('switches the authenticator to SNOWFLAKE_JWT', function () {
    $keyFile = tempnam(sys_get_temp_dir(), 'sf_test_key_');
    file_put_contents($keyFile, 'PEM CONTENT');

    [$config] = configureKeyPairAuth([
        'driver' => 'snowflake',
        'authenticator' => 'key_pair',
        'private_key_path' => $keyFile,
    ]);

    expect($config['authenticator'])->toBe('SNOWFLAKE_JWT')
        ->and($config['PRIV_KEY_FILE'])->toBe($keyFile);

    unlink($keyFile);
});

it('uses lowercase DSN keys for the native driver', function () {
    $keyFile = tempnam(sys_get_temp_dir(), 'sf_test_key_');
    file_put_contents($keyFile, 'PEM CONTENT');

    [$config] = configureKeyPairAuth([
        'driver' => 'snowflake_native',
        'authenticator' => 'key_pair',
        'private_key_path' => $keyFile,
        'private_key_passphrase' => 'passphrase',
    ], true);

    expect($config['priv_key_file'])->toBe($keyFile)
        ->and($config['priv_key_file_pwd'])->toBe('passphrase')
        ->and($config)->not->toHaveKeys(['PRIV_KEY_FILE', 'private_key_passphrase']);

    unlink($keyFile);
});

it('writes an inline private key to a secured temporary file', function () {
    [$config, $temporaryKeyFile] = configureKeyPairAuth([
        'driver' => 'snowflake',
        'authenticator' => 'key_pair',
        'private_key' => '-----BEGIN PRIVATE KEY-----',
    ]);

    expect($temporaryKeyFile)->not->toBeNull()
        ->and(file_get_contents($temporaryKeyFile))->toBe('-----BEGIN PRIVATE KEY-----')
        ->and(substr(sprintf('%o', fileperms($temporaryKeyFile)), -4))->toBe('0600')
        ->and($config['PRIV_KEY_FILE'])->toBe($temporaryKeyFile);

    unlink($temporaryKeyFile);
});

it('strips configuration keys that do not belong in the DSN', function () {
    $keyFile = tempnam(sys_get_temp_dir(), 'sf_test_key_');
    file_put_contents($keyFile, 'PEM CONTENT');

    [$config] = configureKeyPairAuth([
        'driver' => 'snowflake',
        'authenticator' => 'key_pair',
        'private_key_path' => $keyFile,
        'account' => 'test-account',
        'username' => 'user',
        'password' => 'should-be-removed',
        'name' => 'snowflake',
        'prefix' => '',
    ]);

    expect($config)->not->toHaveKeys(['password', 'name', 'prefix', 'private_key_path'])
        ->and($config['account'])->toBe('test-account')
        ->and($config['username'])->toBe('user');

    unlink($keyFile);
});
