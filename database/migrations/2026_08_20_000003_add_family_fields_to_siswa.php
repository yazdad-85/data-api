<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('siswa', 'status_keluarga')) {
            return;
        }

        Schema::table('siswa', function (Blueprint $table) {
            $table->string('status_keluarga', 20)->nullable()->after('alamat');
            $table->string('nama_ayah', 150)->nullable()->after('status_keluarga');
            $table->string('pekerjaan_ayah', 100)->nullable()->after('nama_ayah');
            $table->string('nama_ibu', 150)->nullable()->after('pekerjaan_ayah');
            $table->string('pekerjaan_ibu', 100)->nullable()->after('nama_ibu');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('siswa', 'status_keluarga')) {
            return;
        }

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn([
                'status_keluarga',
                'nama_ayah',
                'pekerjaan_ayah',
                'nama_ibu',
                'pekerjaan_ibu',
            ]);
        });
    }
};
