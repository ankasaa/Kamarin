<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->guardTestDatabase();

        parent::setUp();
    }

    /**
     * Refuse to run the test at all unless the target database is a test database.
     *
     * This runs before the application boots, therefore before RefreshDatabase can
     * execute migrate:fresh (which drops every table). A non "_test" database name
     * aborts the test with an error instead of wiping development data.
     * See AGENTS.md rule 10 and docs/rencana_pengujian.md section 2.
     */
    private function guardTestDatabase(): void
    {
        $database = Env::get('DB_DATABASE');

        if (! is_string($database) || $database === '' || ! str_ends_with($database, '_test')) {
            $name = is_string($database) && $database !== '' ? $database : '(not set)';

            throw new RuntimeException(
                "Refusing to run tests against database [{$name}]. "
                .'The database name must end with "_test" so that tests can never drop development data. '
                .'Fix DB_DATABASE in phpunit.xml before running the suite again.'
            );
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
