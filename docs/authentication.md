# Authentication

This page tells you how to authenticate a connection to Snowflake.

The package supports two methods:

- Password authentication.
- Key-pair authentication. We recommend this method for production.

Both methods work with the native driver and with the Snowflake ODBC driver.

## Password authentication

Set the `username` and the `password` keys:

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    'account' => env('SNOWFLAKE_ACCOUNT'),
    'username' => env('SNOWFLAKE_USERNAME'),
    'password' => env('SNOWFLAKE_PASSWORD'),
    // ...
],
```

> **NOTE:** Snowflake can require multi-factor authentication for users that
> use a password. An application cannot complete multi-factor
> authentication. Use key-pair authentication for service users.

## Key-pair authentication

With key-pair authentication, the application uses a private key. Snowflake
keeps the public key. Refer to the
[Snowflake key-pair documentation](https://docs.snowflake.com/en/user-guide/key-pair-auth).

### Prepare the key pair

1. Make a private key:

   ```bash
   openssl genrsa 2048 | openssl pkcs8 -topk8 -inform PEM -out rsa_key.p8 -nocrypt
   ```

   To encrypt the private key with a passphrase, remove `-nocrypt`.

2. Make the public key from the private key:

   ```bash
   openssl rsa -in rsa_key.p8 -pubout -out rsa_key.pub
   ```

3. Give the public key to the Snowflake user:

   ```sql
   ALTER USER LARAVEL_APP SET RSA_PUBLIC_KEY='MIIBIjANBgkqh...';
   ```

   Use the contents of `rsa_key.pub` without the first line and the last
   line.

### Configure the connection

1. Set `authenticator` to `key_pair`.
2. Give the private key in one of these two keys:
   - `private_key_path`: The path to a PEM file that contains the key.
   - `private_key`: The contents of the key.
3. If the key is encrypted, set `private_key_passphrase`.

```php
'snowflake' => [
    'driver' => 'snowflake_native',
    'account' => env('SNOWFLAKE_ACCOUNT'),
    'username' => env('SNOWFLAKE_USERNAME'),
    'authenticator' => 'key_pair',

    // Give one of these two keys.
    'private_key_path' => env('SNOWFLAKE_PRIVATE_KEY_PATH'),
    'private_key' => env('SNOWFLAKE_PRIVATE_KEY'),

    // Only for an encrypted key.
    'private_key_passphrase' => env('SNOWFLAKE_PRIVATE_KEY_PASSPHRASE'),

    'database' => env('SNOWFLAKE_DATABASE'),
    'warehouse' => env('SNOWFLAKE_WAREHOUSE'),
    'schema' => env('SNOWFLAKE_SCHEMA', 'PUBLIC'),
],
```

If you set both keys, the package uses `private_key_path`.

### Key as a file or as a value

Use `private_key_path` when your servers can keep the key in a file. Make
sure that the PHP process can read the file. If the package cannot read the
file, the connection fails with an error.

Use `private_key` when your platform gives secrets as environment variables.
The driver can only read a key from a file. Thus, the package does these
steps for each new connection:

1. It writes the key to a temporary file. Only the owner can read the file
   (permissions `0600`).
2. It gives the path of the file to the driver.
3. It deletes the file immediately after the connection opens or fails.

To keep a key with more than one line in an environment variable, put the
value in double quotes:

```dotenv
SNOWFLAKE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQ...
-----END PRIVATE KEY-----"
```

### What the package sends to the driver

With key-pair authentication, the package:

- Sets the Snowflake authenticator to `SNOWFLAKE_JWT`.
- Gives the key path in the key name that the driver expects:
  `priv_key_file` for the native driver and `PRIV_KEY_FILE` for the ODBC
  driver.
- Does not send the `password` key or the key material to the DSN.

All other connection keys go to the DSN, as with password authentication.
Refer to [Configuration](configuration.md#other-dsn-parameters).
