<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table): void {
            $table->string('nama_kepala', 150)->nullable()->after('nama');
            $table->string('kop_surat_path')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table): void {
            $table->dropColumn(['nama_kepala', 'kop_surat_path']);
        });
    }
};
