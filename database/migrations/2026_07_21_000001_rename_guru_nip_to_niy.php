<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('guru', 'nip')) {
            return;
        }

        try {
            Schema::table('guru', function (Blueprint $table) {
                $table->renameColumn('nip', 'niy');
            });
        } catch (Throwable) {
            DB::statement('ALTER TABLE guru RENAME COLUMN nip TO niy');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('guru', 'niy')) {
            return;
        }

        try {
            Schema::table('guru', function (Blueprint $table) {
                $table->renameColumn('niy', 'nip');
            });
        } catch (Throwable) {
            DB::statement('ALTER TABLE guru RENAME COLUMN niy TO nip');
        }
    }
};
