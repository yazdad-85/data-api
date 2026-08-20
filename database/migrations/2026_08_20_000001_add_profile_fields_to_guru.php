<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->string('nik', 30)->nullable()->after('niy');
            $table->string('pendidikan_terakhir', 10)->nullable()->after('tahun_masuk');
            $table->string('instansi_pendidikan', 150)->nullable()->after('pendidikan_terakhir');
            $table->string('jurusan', 100)->nullable()->after('instansi_pendidikan');
            $table->string('status_sertifikasi', 10)->nullable()->after('jurusan');
            $table->string('status_inpasing', 10)->nullable()->after('status_sertifikasi');
            $table->string('mapel_sertifikasi', 100)->nullable()->after('status_inpasing');
            $table->string('status_menikah', 30)->nullable()->after('mapel_sertifikasi');
            $table->index(['lembaga_id', 'nik']);
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropIndex(['lembaga_id', 'nik']);
            $table->dropColumn([
                'nik',
                'pendidikan_terakhir',
                'instansi_pendidikan',
                'jurusan',
                'status_sertifikasi',
                'status_inpasing',
                'mapel_sertifikasi',
                'status_menikah',
            ]);
        });
    }
};
