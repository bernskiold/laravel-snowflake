<?php

it('compiles atTimestamp into an AT clause', function () {
    $sql = $this->makeConnection()->query()
        ->from('users')
        ->atTimestamp('2026-06-01 12:00:00')
        ->toSql();

    expect($sql)->toBe("select * from USERS at (timestamp => '2026-06-01 12:00:00'::timestamp_tz)");
});

it('formats DateTime instances for atTimestamp', function () {
    $sql = $this->makeConnection()->query()
        ->from('users')
        ->atTimestamp(new DateTime('2026-06-01 12:00:00', new DateTimeZone('UTC')))
        ->toSql();

    expect($sql)->toBe("select * from USERS at (timestamp => '2026-06-01 12:00:00.000000+00:00'::timestamp_tz)");
});

it('compiles beforeStatement into a BEFORE clause', function () {
    $sql = $this->makeConnection()->query()
        ->from('users')
        ->beforeStatement('8e5d0ca9-005e-44e6-b858-a8f5b37c5726')
        ->toSql();

    expect($sql)->toBe("select * from USERS before (statement => '8e5d0ca9-005e-44e6-b858-a8f5b37c5726')");
});

it('combines time travel with where clauses', function () {
    $sql = $this->makeConnection()->query()
        ->from('users')
        ->atTimestamp('2026-06-01 12:00:00')
        ->where('name', 'Jane')
        ->toSql();

    expect($sql)->toBe("select * from USERS at (timestamp => '2026-06-01 12:00:00'::timestamp_tz) where NAME = ?");
});
