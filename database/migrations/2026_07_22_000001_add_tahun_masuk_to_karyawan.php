<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_masuk')->nullable()->after('nik_pegawai');
            $table->index(['lembaga_id', 'tahun_masuk']);
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropIndex(['lembaga_id', 'tahun_masuk']);
            $table->dropColumn('tahun_masuk');
        });
    }
};
