# Semi-structured data

This page tells you how to write and read data in `VARIANT`, `OBJECT` and
`ARRAY` columns.

## Make a column for semi-structured data

The `json()` and `jsonb()` methods in a migration make a `VARIANT` column:

```php
Schema::connection('snowflake')->create('countries', function (Blueprint $table) {
    $table->string('code', 2)->primary();
    $table->json('name');
});
```

## Write semi-structured data

Snowflake does not change a string to a `VARIANT` value automatically. The
value must go through the `PARSE_JSON()` function. If you write a JSON
string directly to a `VARIANT` column, Snowflake gives an error.

Thus, you must mark each semi-structured value. Use one of these two
methods:

- `Snowflake::variant()` in the query builder.
- The `AsVariant` cast on Eloquent models.

### Query builder

Put the value in `Snowflake::variant()`:

```php
use Bernskiold\LaravelSnowflake\Snowflake;

DB::connection('snowflake')->table('countries')->insert([
    'code' => 'DE',
    'name' => Snowflake::variant(['en' => 'Germany', 'de' => 'Deutschland']),
]);
```

`Snowflake::variant()` accepts these values:

| Value | Result |
| --- | --- |
| An array or an object | The package encodes the value as JSON. |
| A string | The package uses the string as JSON that is already encoded. |
| `null` | The column gets SQL `NULL`. |

> **CAUTION:** A string goes to Snowflake without changes. Thus, the string
> must be valid JSON. To keep a plain text value, put it in an array, or use
> `json_encode()` on it first.

### Eloquent

Add the `AsVariant` cast to the attribute:

```php
use Bernskiold\LaravelSnowflake\Casts\AsVariant;

class Country extends Model
{
    protected $connection = 'snowflake';

    protected function casts(): array
    {
        return [
            'name' => AsVariant::class,
        ];
    }
}

Country::create([
    'code' => 'DE',
    'name' => ['en' => 'Germany', 'de' => 'Deutschland'],
]);
```

The cast does these steps:

- When you write the attribute, it puts the value in `Snowflake::variant()`.
- When you read the attribute, it decodes the JSON to a PHP array.

### Write methods that support semi-structured data

You can use semi-structured values and other values in the same statement.
These methods support semi-structured values:

| Method | Compiled SQL |
| --- | --- |
| `insert()` and `insertGetId()` | `insert into ... select column1, parse_json(column2) from values (?, ?)` |
| `update()` | `update ... set NAME = PARSE_JSON(?)` |
| `upsert()` | `merge into ... using (select column1 as CODE, parse_json(column2) as NAME from values (?, ?))` |

Snowflake does not accept functions in a `VALUES` clause. Thus, an insert
with a semi-structured value uses `SELECT ... FROM VALUES`. An insert
without a semi-structured value uses a usual `VALUES` clause.

With the native driver, the JSON value goes to Snowflake as a bound
parameter. The ODBC driver puts escaped values into the SQL text. Refer to
[ODBC connections](odbc.md#bound-values).

## Read semi-structured data

Snowflake gives a `VARIANT` value as a JSON string.

- With the `AsVariant` cast, you get a PHP array.
- Without the cast, you get the JSON string. Use `json_decode()` to decode
  it.

## Query semi-structured data

The JSON methods of the query builder compile to Snowflake functions:

```php
// get_path(SETTINGS, 'theme') = ?
User::where('settings->theme', 'dark')->get();

// get_path(SETTINGS, 'theme.color') = ?
User::where('settings->theme->color', 'blue')->get();

// array_contains(parse_json(?), TAGS)
User::whereJsonContains('tags', 'admin')->get();

// get_path(SETTINGS, 'theme') is not null
User::whereJsonContainsKey('settings->theme')->get();

// array_size(TAGS) > ?
User::whereJsonLength('tags', '>', 2)->get();
```

## Update semi-structured data

You cannot update one key in a `VARIANT` column. This statement gives an
error:

```php
// RuntimeException
DB::table('users')->update(['settings->theme' => 'dark']);
```

Read the full value, change it, and write the full value again:

```php
$user = User::find($id);

$user->settings = array_merge($user->settings, ['theme' => 'dark']);
$user->save();
```

## Test semi-structured data

The test suite of the package examines the compiled SQL. It does not run
the SQL on Snowflake. Thus, we recommend that you write integration tests
that write and read a `VARIANT` column on a real Snowflake connection.
