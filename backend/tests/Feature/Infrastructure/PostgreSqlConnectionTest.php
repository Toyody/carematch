<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgreSqlConnectionTest extends TestCase
{
    public function test_the_application_connects_to_the_postgresql_test_database(): void
    {
        $connection = DB::selectOne('select current_database() as database_name');

        self::assertNotNull($connection);
        self::assertSame('carematch_test', $connection->database_name);
    }
}
