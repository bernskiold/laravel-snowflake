<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Case-Sensitive Identifiers
    |--------------------------------------------------------------------------
    |
    | Snowflake folds unquoted identifiers to uppercase. By default this
    | package follows that convention: all table and column names are
    | uppercased and left unquoted. Enable this option to wrap identifiers
    | in double quotes instead, keeping the casing used in your queries
    | and migrations.
    |
    | Can be overridden per connection via the "case_sensitive" key in the
    | connection's "options" array.
    |
    */

    'case_sensitive' => env('SNOWFLAKE_COLUMNS_CASE_SENSITIVE', false),

    /*
    |--------------------------------------------------------------------------
    | Case-Insensitive LIKE
    |--------------------------------------------------------------------------
    |
    | When enabled (the default), LIKE clauses compile to Snowflake's
    | case-insensitive ILIKE, which matches the behaviour most Laravel
    | applications expect from other databases. Disable it to keep
    | Snowflake's case-sensitive LIKE semantics.
    |
    | Can be overridden per connection via the "use_ilike" key in the
    | connection's "options" array.
    |
    */

    'use_ilike' => env('SNOWFLAKE_USE_ILIKE', true),

    /*
    |--------------------------------------------------------------------------
    | Force Case-Sensitive Quoted Identifiers
    |--------------------------------------------------------------------------
    |
    | When enabled (the default), every new connection runs
    | "ALTER SESSION SET QUOTED_IDENTIFIERS_IGNORE_CASE = false" so quoted
    | identifiers keep their case and the grammar's quoting semantics hold
    | regardless of the account-level setting.
    |
    | Can be overridden per connection via the "force_quoted_identifiers"
    | key in the connection's "options" array.
    |
    */

    'force_quoted_identifiers' => env(
        'SNOWFLAKE_FORCE_QUOTED_IDENTIFIERS',
        ! env('SNOWFLAKE_DISABLE_FORCE_QUOTED_IDENTIFIER', false)
    ),

];
