<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProductionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_cache_tables_exist(): void
    {
        self::assertTrue(Schema::hasTable('cache'));
        self::assertTrue(Schema::hasTable('cache_locks'));
    }

    public function test_candidate_document_s3_disk_is_private_and_fails_loudly(): void
    {
        $disk = config('filesystems.disks.candidate_documents_s3');

        self::assertIsArray($disk);
        self::assertSame('s3', $disk['driver'] ?? null);
        self::assertSame('private', $disk['visibility'] ?? null);
        self::assertTrue($disk['throw'] ?? false);
        self::assertArrayNotHasKey('url', $disk);
        self::assertArrayNotHasKey('key', $disk);
        self::assertArrayNotHasKey('secret', $disk);
    }
}
