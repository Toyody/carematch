<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        foreach (['candidates', 'jobs'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN latitude numeric(8, 6) NULL");
            DB::statement("ALTER TABLE {$table} ADD COLUMN longitude numeric(9, 6) NULL");
            DB::statement(<<<SQL
                ALTER TABLE {$table}
                ADD COLUMN location_geography geography(Point, 4326)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN latitude IS NULL OR longitude IS NULL THEN NULL
                        ELSE ST_SetSRID(ST_MakePoint(longitude::double precision, latitude::double precision), 4326)::geography
                    END
                ) STORED
                SQL);
            DB::statement(<<<SQL
                ALTER TABLE {$table}
                ADD CONSTRAINT {$table}_coordinates_pair_check
                CHECK ((latitude IS NULL) = (longitude IS NULL))
                SQL);
            DB::statement(<<<SQL
                ALTER TABLE {$table}
                ADD CONSTRAINT {$table}_latitude_check
                CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90)
                SQL);
            DB::statement(<<<SQL
                ALTER TABLE {$table}
                ADD CONSTRAINT {$table}_longitude_check
                CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)
                SQL);
        }

        DB::statement('CREATE INDEX candidates_location_geography_gist ON candidates USING GIST (location_geography)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS candidates_location_geography_gist');

        foreach (['jobs', 'candidates'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS location_geography");
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS longitude");
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS latitude");
        }

        // PostGIS is a shared database capability. Do not drop the extension on rollback.
    }
};
