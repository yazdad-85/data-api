<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('siswa', 'status_keluarga')) {
            return;
        }

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE siswa ALTER COLUMN status_keluarga TYPE VARCHAR(50)'),
            'mysql' => DB::statement('ALTER TABLE siswa MODIFY status_keluarga VARCHAR(50) NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasColumn('siswa', 'status_keluarga')) {
            return;
        }

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE siswa ALTER COLUMN status_keluarga TYPE VARCHAR(20)'),
            'mysql' => DB::statement('ALTER TABLE siswa MODIFY status_keluarga VARCHAR(20) NULL'),
            default => null,
        };
    }
};
