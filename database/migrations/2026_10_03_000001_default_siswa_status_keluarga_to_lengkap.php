<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('siswa')
            ->whereNull('status_keluarga')
            ->orWhereRaw("TRIM(status_keluarga) = ''")
            ->update(['status_keluarga' => 'Lengkap']);

        Schema::table('siswa', function (Blueprint $table) {
            $table->string('status_keluarga', 50)->nullable()->default('Lengkap')->change();
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('status_keluarga', 50)->nullable()->default(null)->change();
        });

        // Keep recorded statuses: backfilled and explicitly selected Lengkap cannot be distinguished.
    }
};
