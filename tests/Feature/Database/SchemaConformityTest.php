<?php

use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| T-004 - Schema conformity test
|--------------------------------------------------------------------------
|
| docs/erd_v2_1.mermaid is the schema source of truth (AGENTS.md rule 1).
| This test parses the ERD file and compares the parsed expectations with
| the real PostgreSQL information_schema of the testing database, so any
| silent schema drift fails the suite instead of being discovered later.
|
| Stage 1 (T-004) migrates the master tables only. The transaction tables
| are kept in an explicit exceptions list until T-005 empties it, as
| required by docs/tasks/BACKLOG_FASE4_5.md (row T-004).
|
*/

/** Tables migrated so far. Stage 2 (T-005) completed the set: all 19 ERD tables now exist. */
function erdMigratedTables(): array
{
    return [
        'roles',
        'users',
        'properties',
        'room_types',
        'rooms',
        'amenities',
        'room_type_amenities',
        'housekeeping_statuses',
        'guests',
        'booking_sources',
        'rates',
        'room_blocks',
        'bookings',
        'booking_items',
        'booking_nights',
        'booking_charges',
        'payments',
        'booking_status_logs',
        'booking_change_logs',
    ];
}

/**
 * Explicit exceptions: transaction tables not migrated until T-005.
 *
 * Emptied by T-005 (2026-10-11): every ERD table is now migrated, so the
 * list stays empty and this function only documents the history.
 */
function erdTransactionExceptions(): array
{
    return [];
}

/** Framework tables allowed outside the ERD (AGENTS.md rule 13). */
function erdFrameworkTables(): array
{
    return [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
    ];
}

/** Columns allowed outside the ERD on master tables (AGENTS.md rule 13). */
function erdAllowedExtraColumns(string $table): array
{
    $standard = ['created_at', 'updated_at'];

    return match ($table) {
        // users keeps the starter kit authentication columns that rule 13
        // explicitly allows outside the ERD.
        'users' => [...$standard, 'remember_token', 'email_verified_at'],
        default => $standard,
    };
}

/** Map an ERD type to the PostgreSQL information_schema data_type. */
function erdTypeToPostgres(string $type): string
{
    return match ($type) {
        'bigint' => 'bigint',
        'varchar' => 'character varying',
        'text' => 'text',
        'boolean' => 'boolean',
        'int' => 'integer',
        'smallint' => 'smallint',
        'timestamp' => 'timestamp without time zone',
        'date' => 'date',
        'time' => 'time without time zone',
        'decimal' => 'numeric',
        'jsonb' => 'jsonb',
        default => throw new RuntimeException("Unknown ERD type [{$type}]"),
    };
}

/** Parse docs/erd_v2_1.mermaid into entity => column metadata. */
function parseErdSchema(): array
{
    $path = base_path('docs/erd_v2_1.mermaid');

    if (! is_readable($path)) {
        throw new RuntimeException("ERD file not readable: {$path}");
    }

    $lines = preg_split('/\R/', file_get_contents($path));
    $schema = [];
    $entity = null;

    foreach ($lines as $line) {
        if (preg_match('/^\s*([A-Z][A-Z_0-9]*)\s*\{\s*$/', $line, $start)) {
            $entity = $start[1];
            $schema[$entity] = [];

            continue;
        }

        if ($entity !== null && preg_match('/^\s*\}\s*$/', $line)) {
            $entity = null;

            continue;
        }

        if ($entity === null || trim($line) === '' || str_starts_with(trim($line), '%%')) {
            continue;
        }

        if (! preg_match('/^(\w+)\s+(\w+)(?:\s+(PK|UK|FK))?(?:\s+"([^"]*)")?\s*$/', trim($line), $field)) {
            throw new RuntimeException("Unparseable ERD field line in [{$entity}]: {$line}");
        }

        $type = $field[1];
        $name = $field[2];
        $comment = trim(($field[3] ?? '').' '.($field[4] ?? ''));

        $schema[$entity][$name] = [
            'type' => $type,
            // ERD marks optional columns "nullable" or "opsional"; a soft
            // delete marker also implies a nullable timestamp.
            'nullable' => str_contains($comment, 'nullable')
                || str_contains($comment, 'opsional')
                || str_contains($comment, 'soft delete'),
            'primary' => str_contains($comment, 'PK'),
            'unique_group' => preg_match('/UK dengan (\w+)/', $comment, $uk) ? $uk[1] : null,
            'unique_alone' => str_contains($comment, 'UK') && ! str_contains($comment, 'UK dengan'),
            // Declared default, e.g. "default 14:00" or "default 3".
            'default' => preg_match('/default\s+([0-9:]+)/', $comment, $dv) ? $dv[1] : null,
        ];
    }

    if (count($schema) !== 19) {
        throw new RuntimeException('Expected 19 entities in the ERD, parsed '.count($schema));
    }

    return $schema;
}

/** Fetch all public columns of a table, keyed by column_name. */
function pgColumns(string $table)
{
    $rows = DB::select('
        SELECT column_name, data_type, is_nullable, column_default, numeric_precision, numeric_scale
        FROM information_schema.columns
        WHERE table_schema = \'public\' AND table_name = ?
        ORDER BY ordinal_position
    ', [$table]);

    return collect($rows)->keyBy('column_name');
}

/** Fetch primary key column names of a table. */
function pgPrimaryKeys(string $table): array
{
    $rows = DB::select("
        SELECT a.attname
        FROM pg_index i
        JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
        WHERE i.indrelid = to_regclass('public.' || ?) AND i.indisprimary
    ", [$table]);

    return array_map(fn ($row) => $row->attname, $rows);
}

/** Fetch the column lists of every unique index of a table. */
function pgUniqueIndexColumns(string $table): array
{
    $indexes = DB::select('
        SELECT indexdef FROM pg_indexes WHERE schemaname = \'public\' AND tablename = ?
    ', [$table]);

    $sets = [];

    foreach ($indexes as $index) {
        if (! str_starts_with($index->indexdef, 'CREATE UNIQUE INDEX')) {
            continue;
        }

        preg_match('/\(([^)]+)\)\s*$/', $index->indexdef, $match);

        $sets[] = array_map('trim', explode(',', $match[1]));
    }

    return $sets;
}

it('creates every master table from the ERD with matching columns, types and nullability', function () {
    $erd = parseErdSchema();

    foreach (erdMigratedTables() as $table) {
        $entity = strtoupper($table);
        $columns = pgColumns($table);

        expect($columns)->not->toBeEmpty("Table [{$table}] does not exist in the testing database");

        foreach ($erd[$entity] as $name => $meta) {
            expect($columns->has($name))->toBeTrue("Table [{$table}] is missing ERD column [{$name}]");
            expect($columns[$name]->data_type)->toBe(
                erdTypeToPostgres($meta['type']),
                "Column [{$table}.{$name}] type does not match ERD type [{$meta['type']}]"
            );

            // ERD header: timestamps are the Laravel standard, which means
            // nullable columns; the per-field nullable markers apply only to
            // business columns.
            if (in_array($name, ['created_at', 'updated_at'], true)) {
                expect($columns[$name]->is_nullable)->toBe('YES',
                    "Timestamp [{$table}.{$name}] must follow the Laravel standard (nullable)"
                );

                continue;
            }

            expect($columns[$name]->is_nullable)->toBe($meta['nullable'] ? 'YES' : 'NO',
                "Column [{$table}.{$name}] nullability does not match ERD"
            );
        }

        // ERD header: every table carries the standard Laravel timestamps.
        expect($columns->has('created_at'))->toBeTrue("Table [{$table}] is missing created_at");
        expect($columns->has('updated_at'))->toBeTrue("Table [{$table}] is missing updated_at");

        expect(pgPrimaryKeys($table))->toBe(['id'], "Table [{$table}] primary key must be [id]");
    }
});

it('keeps the users table in sync with the ERD plus rule 13 allowances', function () {
    $columns = pgColumns('users');

    expect(array_keys($columns->all()))->toEqualCanonicalizing([
        'id',
        'role_id',
        'property_id',
        'name',
        'email',
        'password',
        'is_active',
        // Allowed by AGENTS.md rule 13 (starter kit defaults):
        'email_verified_at',
        'remember_token',
        'created_at',
        'updated_at',
    ]);
});

it('rejects any column outside the ERD except rule 13 allowances', function () {
    $erd = parseErdSchema();

    foreach (erdMigratedTables() as $table) {
        $entity = strtoupper($table);
        $expected = [
            ...array_keys($erd[$entity]),
            ...erdAllowedExtraColumns($table),
        ];

        foreach (array_keys(pgColumns($table)->all()) as $name) {
            expect($name)->toBeIn(
                $expected,
                "Column [{$table}.{$name}] does not exist in ERD v2.1 and is not allowed by AGENTS.md rule 13"
            );
        }
    }
});

it('rejects any table outside the ERD except framework tables and declared exceptions', function () {
    $erdTables = array_map('strtolower', array_keys(parseErdSchema()));
    $allowed = [...$erdTables, ...erdFrameworkTables(), ...erdTransactionExceptions()];

    $tables = array_map(fn ($row) => $row->tablename, DB::select("
        SELECT tablename FROM pg_tables WHERE schemaname = 'public'
    "));

    foreach ($tables as $table) {
        expect($table)->toBeIn(
            $allowed,
            "Table [{$table}] does not exist in ERD v2.1 and is not allowed by AGENTS.md rule 13"
        );
    }
});

it('enforces the unique constraints marked in the ERD', function () {
    $single = [
        'roles' => ['name'],
        'users' => ['email'],
        'amenities' => ['name'],
        'booking_sources' => ['name'],
        'housekeeping_statuses' => ['room_id'],
        'bookings' => ['booking_code'],
    ];

    foreach ($single as $table => $columns) {
        $found = collect(pgUniqueIndexColumns($table))->contains($columns);

        expect($found)->toBeTrue("Missing unique index on [{$table}.{$columns[0]}]");
    }

    $composite = [
        'rooms' => ['property_id', 'room_number'],
        'room_type_amenities' => ['room_type_id', 'amenity_id'],
    ];

    foreach ($composite as $table => $columns) {
        sort($columns);
        $found = collect(pgUniqueIndexColumns($table))->contains(function ($set) use ($columns) {
            sort($set);

            return $set === $columns;
        });

        expect($found)->toBeTrue("Missing composite unique index on [{$table}]");
    }
});

it('creates the foreign keys declared in the ERD', function () {
    $rows = DB::select("
        SELECT kcu.table_name, kcu.column_name, ccu.table_name AS foreign_table
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu
            ON kcu.constraint_name = tc.constraint_name AND kcu.table_schema = tc.table_schema
        JOIN information_schema.constraint_column_usage ccu
            ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
        WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public'
    ");

    $actual = [];

    foreach ($rows as $row) {
        $actual[] = "{$row->table_name}.{$row->column_name}->{$row->foreign_table}";
    }

    $expected = [
        // Master tables (T-004)
        'users.role_id->roles',
        'users.property_id->properties',
        'room_types.property_id->properties',
        'rooms.property_id->properties',
        'rooms.room_type_id->room_types',
        'room_type_amenities.room_type_id->room_types',
        'room_type_amenities.amenity_id->amenities',
        'housekeeping_statuses.room_id->rooms',
        'housekeeping_statuses.updated_by_user_id->users',
        'rates.property_id->properties',
        'rates.room_type_id->room_types',
        // Transaction tables (T-005)
        'room_blocks.room_id->rooms',
        'room_blocks.created_by_user_id->users',
        'bookings.property_id->properties',
        'bookings.guest_id->guests',
        'bookings.created_by_user_id->users',
        'bookings.booking_source_id->booking_sources',
        'booking_items.booking_id->bookings',
        'booking_items.room_id->rooms',
        'booking_nights.booking_item_id->booking_items',
        'booking_nights.room_id->rooms',
        'booking_nights.rate_id->rates',
        'booking_charges.booking_id->bookings',
        'payments.booking_id->bookings',
        'payments.recorded_by_user_id->users',
        'booking_status_logs.booking_id->bookings',
        'booking_status_logs.changed_by_user_id->users',
        'booking_change_logs.booking_id->bookings',
        'booking_change_logs.changed_by_user_id->users',
    ];

    foreach ($expected as $foreignKey) {
        expect(in_array($foreignKey, $actual, true))->toBeTrue("Missing foreign key [{$foreignKey}]");
    }
});

it('stores every money column as whole rupiah decimal(12,0)', function () {
    $erd = parseErdSchema();
    $checked = 0;

    foreach (erdMigratedTables() as $table) {
        $columns = pgColumns($table);

        foreach ($erd[strtoupper($table)] as $name => $meta) {
            if ($meta['type'] !== 'decimal') {
                continue;
            }

            $checked++;

            expect($columns[$name]->data_type)->toBe('numeric', "Money column [{$table}.{$name}] must be numeric")
                ->and((int) $columns[$name]->numeric_precision)->toBe(12, "Money column [{$table}.{$name}] precision must be 12")
                ->and((int) $columns[$name]->numeric_scale)->toBe(0, "Money column [{$table}.{$name}] scale must be 0 (whole rupiah)");
        }
    }

    // Guard: rates.price, bookings.total_amount, booking_items.subtotal,
    // booking_nights.price, booking_charges.amount, payments.amount.
    expect($checked)->toBe(6);
});

it('applies every default value declared in the ERD', function () {
    $erd = parseErdSchema();
    $checked = 0;

    foreach (erdMigratedTables() as $table) {
        $columns = pgColumns($table);

        foreach ($erd[strtoupper($table)] as $name => $meta) {
            if ($meta['default'] === null) {
                continue;
            }

            $checked++;

            expect(str_contains((string) $columns[$name]->column_default, $meta['default']))->toBeTrue(
                "Column [{$table}.{$name}] must default to [{$meta['default']}] per the ERD"
            );
        }
    }

    // Guard: the parser must actually find the six property policy defaults,
    // otherwise the loop above would pass vacuously.
    expect($checked)->toBe(6);
});
