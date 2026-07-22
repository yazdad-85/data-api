<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->char('niy_kode', 2)->nullable()->unique()->after('kode');
        });

        Schema::table('guru', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_masuk')->nullable()->after('niy');
            $table->index(['lembaga_id', 'tahun_masuk']);
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropIndex(['lembaga_id', 'tahun_masuk']);
            $table->dropColumn('tahun_masuk');
        });

        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropUnique(['niy_kode']);
            $table->dropColumn('niy_kode');
        });
    }
};
