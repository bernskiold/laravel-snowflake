<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

function schemaConnection(array $config = [])
{
    $connection = test()->makeConnection($config);
    $connection->useDefaultSchemaGrammar();

    return $connection;
}

function blueprint(string $table, ?Closure $callback = null, array $config = []): Blueprint
{
    return new Blueprint(schemaConnection($config), $table, $callback);
}

it('compiles a create table statement', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->create();
        $table->increments('id');
        $table->string('name');
    })->toSql();

    expect($statements)->toBe([
        'create table USERS (ID int autoincrement not null primary key, NAME varchar(255) not null)',
    ]);
});

it('compiles a temporary table', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->create();
        $table->temporary();
        $table->string('name');
    })->toSql();

    expect($statements[0])->toStartWith('create temporary table USERS');
});

it('compiles an add column statement', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->string('email')->nullable();
    })->toSql();

    expect($statements)->toBe(['alter table USERS add column EMAIL varchar(255)']);
});

it('compiles one statement per added column with defaults before not null', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->boolean('active')->default(true);
        $table->integer('logins')->default(0);
        $table->string('role')->default('user');
    })->toSql();

    expect($statements)->toBe([
        'alter table USERS add column ACTIVE boolean default TRUE not null',
        'alter table USERS add column LOGINS int default 0 not null',
        "alter table USERS add column ROLE varchar(255) default 'user' not null",
    ]);
});

it('escapes single quotes in string defaults', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->string('quote')->default("it's");
    })->toSql();

    expect($statements)->toBe(["alter table USERS add column QUOTE varchar(255) default 'it''s' not null"]);
});

it('compiles raw expression defaults', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->string('token')->default(DB::raw('uuid_string()'));
    })->toSql();

    expect($statements)->toBe(['alter table USERS add column TOKEN varchar(255) default uuid_string() not null']);
});

it('compiles column comments', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->string('name')->comment('Full name');
    })->toSql();

    expect($statements)->toBe(["alter table USERS add column NAME varchar(255) comment 'Full name' not null"]);
});

it('compiles change column statements', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->string('name', 100)->nullable()->change();
    })->toSql();

    expect($statements)->toBe([
        'alter table USERS modify column NAME varchar(100)',
        'alter table USERS modify column NAME drop not null',
    ]);
});

it('compiles drop table statements', function () {
    $grammar = schemaConnection()->getSchemaGrammar();
    $table = blueprint('users');

    expect($grammar->compileDrop($table, new Fluent))->toBe('drop table USERS')
        ->and($grammar->compileDropIfExists($table, new Fluent))->toBe('drop table if exists USERS');
});

it('compiles a rename table statement', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->rename('accounts');
    })->toSql();

    expect($statements)->toBe(['alter table USERS rename to ACCOUNTS']);
});

it('compiles a rename column statement', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->renameColumn('name', 'full_name');
    })->toSql();

    expect($statements)->toBe(['alter table USERS rename column NAME to FULL_NAME']);
});

it('quotes identifiers when case sensitivity is enabled', function () {
    $this->setColumnsCaseSensitive(true);

    $statements = blueprint('users', function (Blueprint $table) {
        $table->string('name');
    })->toSql();

    expect($statements)->toBe(['alter table "users" add column "name" varchar(255) not null']);
});

// ---------------------------------------------------------------- data types

it('maps column types to Snowflake types', function (callable $define, string $expectedType) {
    $statements = blueprint('t', function (Blueprint $table) use ($define) {
        $define($table)->nullable();
    })->toSql();

    expect($statements)->toBe(["alter table T add column C {$expectedType}"]);
})->with([
    'string' => [fn (Blueprint $t) => $t->string('c'), 'varchar(255)'],
    'text' => [fn (Blueprint $t) => $t->text('c'), 'text'],
    'mediumText' => [fn (Blueprint $t) => $t->mediumText('c'), 'text'],
    'longText' => [fn (Blueprint $t) => $t->longText('c'), 'text'],
    'json' => [fn (Blueprint $t) => $t->json('c'), 'variant'],
    'jsonb' => [fn (Blueprint $t) => $t->jsonb('c'), 'variant'],
    'binary' => [fn (Blueprint $t) => $t->binary('c'), 'binary'],
    'enum' => [fn (Blueprint $t) => $t->enum('c', ['a', 'b']), 'varchar'],
    'year' => [fn (Blueprint $t) => $t->year('c'), 'smallint'],
    'float' => [fn (Blueprint $t) => $t->float('c', 8), 'float'],
    'double' => [fn (Blueprint $t) => $t->double('c'), 'double'],
    'decimal' => [fn (Blueprint $t) => $t->decimal('c', 10, 2), 'decimal(10, 2)'],
    'boolean' => [fn (Blueprint $t) => $t->boolean('c'), 'boolean'],
    'date' => [fn (Blueprint $t) => $t->date('c'), 'date'],
    'dateTime' => [fn (Blueprint $t) => $t->dateTime('c', null), 'timestamp_ntz'],
    'dateTimeTz' => [fn (Blueprint $t) => $t->dateTimeTz('c', null), 'timestamp_tz'],
    'timestamp' => [fn (Blueprint $t) => $t->timestamp('c', null), 'timestamp_ntz'],
    'timestampTz' => [fn (Blueprint $t) => $t->timestampTz('c', null), 'timestamp_tz'],
    'time' => [fn (Blueprint $t) => $t->time('c', null), 'time'],
    'uuid' => [fn (Blueprint $t) => $t->uuid('c'), 'char(36)'],
    'ipAddress' => [fn (Blueprint $t) => $t->ipAddress('c'), 'varchar(45)'],
    'geometry' => [fn (Blueprint $t) => $t->geometry('c'), 'geometry'],
    'geography' => [fn (Blueprint $t) => $t->geography('c'), 'geography'],
]);

it('compiles timestamps with precision and useCurrent', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->timestamp('created_at', 6)->useCurrent()->nullable();
    })->toSql();

    expect($statements)->toBe(['alter table USERS add column CREATED_AT timestamp_ntz(6) default CURRENT_TIMESTAMP(6)']);
});

// ------------------------------------------------------- keys and constraints

it('compiles unique constraints', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->unique('email', 'users_email_unique');
    })->toSql();

    expect($statements)->toBe(['alter table USERS add constraint USERS_EMAIL_UNIQUE unique (EMAIL)']);
});

it('compiles primary key constraints', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->primary(['id'], 'users_pk');
    })->toSql();

    expect($statements)->toBe(['alter table USERS add constraint USERS_PK primary key (ID)']);
});

it('compiles foreign key constraints', function () {
    $statements = blueprint('posts', function (Blueprint $table) {
        $table->foreign('user_id', 'posts_user_fk')->references('id')->on('users');
    })->toSql();

    expect($statements)->toBe(['alter table POSTS add constraint POSTS_USER_FK foreign key (USER_ID) references USERS (ID)']);
});

it('skips index commands because Snowflake has no indexes', function () {
    $statements = blueprint('users', function (Blueprint $table) {
        $table->index('email', 'users_email_index');
    })->toSql();

    expect($statements)->toBe([]);
});

it('skips drop index and rename index commands', function () {
    $dropStatements = blueprint('users', function (Blueprint $table) {
        $table->dropIndex('users_email_index');
    })->toSql();

    $renameStatements = blueprint('users', function (Blueprint $table) {
        $table->renameIndex('old_index', 'new_index');
    })->toSql();

    expect($dropStatements)->toBe([])
        ->and($renameStatements)->toBe([]);
});

it('drops unique and foreign constraints by name', function () {
    $grammar = schemaConnection()->getSchemaGrammar();
    $table = blueprint('users');

    expect($grammar->compileDropUnique($table, new Fluent(['index' => 'users_email_unique'])))
        ->toBe('alter table USERS drop constraint USERS_EMAIL_UNIQUE')
        ->and($grammar->compileDropForeign($table, new Fluent(['index' => 'users_fk'])))
        ->toBe('alter table USERS drop constraint USERS_FK');
});

it('compiles foreign key toggles as harmless no-ops', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    expect($grammar->compileEnableForeignKeyConstraints())->toBe('select 1')
        ->and($grammar->compileDisableForeignKeyConstraints())->toBe('select 1');
});

// ------------------------------------------------------------- introspection

it('compiles the table exists query', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    expect($grammar->compileTableExists('TESTDB', 'USERS'))->toBe(
        "select * from TESTDB.information_schema.tables where table_name = 'USERS' and table_type = 'BASE TABLE'"
    );
});

it('filters the table exists query by schema', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    expect($grammar->compileTableExists('TESTDB', 'USERS', 'PUBLIC'))->toBe(
        "select * from TESTDB.information_schema.tables where table_name = 'USERS' and table_type = 'BASE TABLE' and table_schema = 'PUBLIC'"
    );
});

it('compiles the tables query against information_schema', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    expect($grammar->compileTables(null))->toBe(
        'select table_name as "name", table_schema as "schema", bytes as "size", comment as "comment" '
        ."from TESTDB.information_schema.tables where table_type = 'BASE TABLE' "
        ."and table_schema != 'INFORMATION_SCHEMA' order by table_name"
    );
});

it('filters the tables query by the requested schema', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    expect($grammar->compileTables('reporting'))->toContain("table_schema in ('REPORTING')");
});

it('uses the connection schema for the tables query when configured', function () {
    $grammar = schemaConnection(['schema' => 'public'])->getSchemaGrammar();

    expect($grammar->compileTables(null))->toContain("table_schema in ('PUBLIC')");
});

it('compiles the columns query against information_schema', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    $sql = $grammar->compileColumns(null, 'users');

    expect($sql)->toContain('from TESTDB.information_schema.columns')
        ->and($sql)->toContain("table_name = 'USERS'")
        ->and($sql)->toContain('order by ordinal_position');
});

it('filters the columns query by schema', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    expect($grammar->compileColumns('reporting', 'users'))->toContain("table_schema = 'REPORTING'");
});

it('compiles the views query against information_schema', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    expect($grammar->compileViews(null))->toContain('from TESTDB.information_schema.views');
});

it('compiles empty result sets for indexes and foreign keys', function () {
    $grammar = schemaConnection()->getSchemaGrammar();

    expect($grammar->compileIndexes(null, 'users'))->toEndWith('limit 0')
        ->and($grammar->compileForeignKeys(null, 'users'))->toEndWith('limit 0');
});
