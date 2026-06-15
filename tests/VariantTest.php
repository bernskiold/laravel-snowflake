<?php

use Bernskiold\LaravelSnowflake\Casts\AsVariant;
use Bernskiold\LaravelSnowflake\Snowflake;
use Bernskiold\LaravelSnowflake\Values\Variant;
use Illuminate\Database\Eloquent\Model;

function variantConnection()
{
    return test()->makeConnection();
}

// ------------------------------------------------------------- value object

it('encodes wrapped values to json', function () {
    expect(Variant::make(['en' => 'Germany'])->toJsonString())->toBe('{"en":"Germany"}')
        ->and(Variant::make(['en', 'de'])->toJsonString())->toBe('["en","de"]');
});

it('treats a string as pre-encoded json', function () {
    expect(Snowflake::variant('{"en":"Germany"}')->toJsonString())->toBe('{"en":"Germany"}');
});

it('preserves null so PARSE_JSON yields SQL NULL', function () {
    expect(Snowflake::variant(null)->toJsonString())->toBeNull();
});

it('is idempotent when wrapping an existing variant', function () {
    $variant = Snowflake::variant(['a' => 1]);

    expect(Variant::make($variant))->toBe($variant);
});

// ------------------------------------------------------------- compiled SQL

it('parses variant columns through a SELECT projection on insert', function () {
    $connection = variantConnection();

    $sql = $connection->getQueryGrammar()->compileInsert($connection->query()->from('countries'), [
        ['code' => 'DE', 'name' => Snowflake::variant(['en' => 'Germany'])],
    ]);

    expect($sql)->toBe('insert into COUNTRIES (CODE, NAME) select column1, parse_json(column2) from values (?, ?)');
});

it('parses variant columns through a SELECT projection for every row of a bulk insert', function () {
    $connection = variantConnection();

    $sql = $connection->getQueryGrammar()->compileInsert($connection->query()->from('countries'), [
        ['code' => 'DE', 'name' => Snowflake::variant(['en' => 'Germany'])],
        ['code' => 'FR', 'name' => Snowflake::variant(['en' => 'France'])],
    ]);

    expect($sql)->toBe('insert into COUNTRIES (CODE, NAME) select column1, parse_json(column2) from values (?, ?), (?, ?)');
});

it('leaves a variant-free insert as a standard VALUES statement', function () {
    $connection = variantConnection();

    $sql = $connection->getQueryGrammar()->compileInsert($connection->query()->from('countries'), [
        ['code' => 'DE', 'population' => 83000000],
    ]);

    expect($sql)->toBe('insert into COUNTRIES (CODE, POPULATION) values (?, ?)');
});

it('wraps variant columns in PARSE_JSON on update', function () {
    $connection = variantConnection();

    $sql = $connection->getQueryGrammar()->compileUpdate($connection->query()->from('countries'), [
        'name' => Snowflake::variant(['en' => 'Germany']),
        'code' => 'DE',
    ]);

    expect($sql)->toBe('update COUNTRIES set NAME = PARSE_JSON(?), CODE = ?');
});

it('wraps variant columns in PARSE_JSON on upsert', function () {
    $connection = variantConnection();

    $sql = $connection->getQueryGrammar()->compileUpsert(
        $connection->query()->from('countries'),
        [['code' => 'DE', 'name' => Snowflake::variant(['en' => 'Germany'])]],
        ['code'],
        ['name']
    );

    expect($sql)->toBe(
        'merge into COUNTRIES using (select column1 as CODE, parse_json(column2) as NAME from values (?, ?)) as laravel_source '
        .'on COUNTRIES.CODE = laravel_source.CODE '
        .'when matched then update set NAME = laravel_source.NAME '
        .'when not matched then insert (CODE, NAME) values (laravel_source.CODE, laravel_source.NAME)'
    );
});

it('wraps a variant value override in PARSE_JSON on upsert', function () {
    $connection = variantConnection();

    $sql = $connection->getQueryGrammar()->compileUpsert(
        $connection->query()->from('countries'),
        [['code' => 'DE', 'name' => Snowflake::variant(['en' => 'Germany'])]],
        ['code'],
        ['name' => Snowflake::variant(['en' => 'Deutschland'])]
    );

    expect($sql)->toContain('when matched then update set NAME = PARSE_JSON(?)');
});

// --------------------------------------------------------------- bindings

it('unwraps variant bindings to json strings in order', function () {
    $bindings = variantConnection()->prepareBindings([
        Snowflake::variant(['en' => 'Germany']),
        'DE',
        Snowflake::variant(['en', 'de']),
        Snowflake::variant(null),
    ]);

    expect($bindings)->toBe(['{"en":"Germany"}', 'DE', '["en","de"]', null]);
});

// ------------------------------------------------------------- eloquent cast

it('casts attributes to a variant on the way in', function () {
    $model = new class extends Model {};
    $cast = new AsVariant;

    $set = $cast->set($model, 'name', ['en' => 'Germany'], []);

    expect($set)->toBeInstanceOf(Variant::class)
        ->and($set->toJsonString())->toBe('{"en":"Germany"}')
        ->and($cast->set($model, 'name', null, []))->toBeNull();
});

it('decodes a variant on the way out', function () {
    $model = new class extends Model {};
    $cast = new AsVariant;

    expect($cast->get($model, 'name', '{"en":"Germany"}', []))->toBe(['en' => 'Germany'])
        ->and($cast->get($model, 'name', null, []))->toBeNull()
        ->and($cast->get($model, 'name', Snowflake::variant(['en' => 'Germany']), []))->toBe(['en' => 'Germany']);
});
