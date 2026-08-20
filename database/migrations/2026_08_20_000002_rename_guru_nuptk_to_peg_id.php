<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('guru', 'nuptk') || Schema::hasColumn('guru', 'peg_id')) {
            return;
        }

        Schema::table('guru', function (Blueprint $table) {
            $table->renameColumn('nuptk', 'peg_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('guru', 'peg_id') || Schema::hasColumn('guru', 'nuptk')) {
            return;
        }

        Schema::table('guru', function (Blueprint $table) {
            $table->renameColumn('peg_id', 'nuptk');
        });
    }
};
