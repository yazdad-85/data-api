<?php

use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('status_siswa', 30)->default(SiswaStatus::AKTIF)->after('is_active');
            $table->date('status_at')->nullable()->after('status_siswa');
            $table->string('status_alasan', 255)->nullable()->after('status_at');
            $table->string('status_asal', 150)->nullable()->after('status_alasan');
            $table->string('status_tujuan', 150)->nullable()->after('status_asal');

            $table->index(['lembaga_id', 'status_siswa']);
        });

        Schema::create('siswa_penempatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lembaga_id');
            $table->uuid('siswa_id');
            $table->uuid('tahun_ajaran_id')->nullable();
            $table->uuid('kelas_id')->nullable();
            $table->date('mulai_at');
            $table->date('selesai_at')->nullable();
            $table->string('jenis', 30);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('lembaga_id')->references('id')->on('lembaga')->restrictOnDelete();
            $table->foreign(['lembaga_id', 'siswa_id'])
                ->references(['lembaga_id', 'id'])
                ->on('siswa')
                ->restrictOnDelete();
            $table->foreign(['lembaga_id', 'tahun_ajaran_id'])
                ->references(['lembaga_id', 'id'])
                ->on('tahun_ajaran')
                ->restrictOnDelete();
            $table->foreign(['lembaga_id', 'tahun_ajaran_id', 'kelas_id'])
                ->references(['lembaga_id', 'tahun_ajaran_id', 'id'])
                ->on('kelas')
                ->restrictOnDelete();

            $table->unique(['lembaga_id', 'id']);
            $table->index(['lembaga_id', 'siswa_id']);
            $table->index(['lembaga_id', 'updated_at', 'id']);
        });

        // Satu baris penempatan terbuka (selesai_at IS NULL) per siswa.
        // Sintaks partial unique index ini didukung baik oleh PostgreSQL maupun SQLite (dipakai di test suite).
        DB::statement(
            'CREATE UNIQUE INDEX siswa_penempatan_satu_terbuka_per_siswa ON siswa_penempatan (lembaga_id, siswa_id) WHERE selesai_at IS NULL'
        );

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_penempatan');

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropIndex(['lembaga_id', 'status_siswa']);
            $table->dropColumn([
                'status_siswa',
                'status_at',
                'status_alasan',
                'status_asal',
                'status_tujuan',
            ]);
        });
    }

    /**
     * Backfill status untuk siswa lama: semua diset `aktif` (kolom baru memakai default),
     * dan setiap siswa yang sudah punya kelas mendapat satu baris penempatan `awal` terbuka.
     */
    private function backfill(): void
    {
        $now = now();

        DB::table('siswa')
            ->whereNotNull('kelas_id')
            ->orderBy('id')
            ->select(['id', 'lembaga_id', 'kelas_id', 'tahun_ajaran_id', 'created_at'])
            ->get()
            ->each(function (object $siswa) use ($now): void {
                DB::table('siswa_penempatan')->insert([
                    'id' => (string) Str::uuid(),
                    'lembaga_id' => $siswa->lembaga_id,
                    'siswa_id' => $siswa->id,
                    'tahun_ajaran_id' => $siswa->tahun_ajaran_id,
                    'kelas_id' => $siswa->kelas_id,
                    'mulai_at' => $siswa->created_at ? substr((string) $siswa->created_at, 0, 10) : $now->toDateString(),
                    'selesai_at' => null,
                    'jenis' => PenempatanJenis::AWAL,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
};
