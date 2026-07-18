<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lembaga_id');
            $table->string('nama', 150);
            $table->string('api_key_prefix', 16)->unique();
            $table->string('api_key_digest', 64);
            $table->json('scopes');
            $table->enum('field_profile', ['minimal', 'academic', 'contact'])->default('minimal');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('lembaga_id')->references('id')->on('lembaga')->restrictOnDelete();
            $table->unique(['lembaga_id', 'id']);
            $table->index(['lembaga_id', 'is_active']);
        });

        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lembaga_id');
            $table->string('nama', 50);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();
            $table->softDeletesTz();

            $table->foreign('lembaga_id')->references('id')->on('lembaga')->restrictOnDelete();
            $table->unique(['lembaga_id', 'id']);
            $table->unique(['lembaga_id', 'nama']);
            $table->index(['lembaga_id', 'updated_at', 'id']);
            $table->index(['lembaga_id', 'deleted_at', 'id']);
        });

        Schema::create('guru', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lembaga_id');
            $table->string('nip', 40)->nullable();
            $table->string('nuptk', 40)->nullable();
            $table->string('nama', 150);
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->string('status_kepegawaian', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletesTz();

            $table->foreign('lembaga_id')->references('id')->on('lembaga')->restrictOnDelete();
            $table->unique(['lembaga_id', 'id']);
            $table->index(['lembaga_id', 'updated_at', 'id']);
            $table->index(['lembaga_id', 'deleted_at', 'id']);
            $table->index(['lembaga_id', 'nama']);
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lembaga_id');
            $table->uuid('tahun_ajaran_id');
            $table->string('nama', 50);
            $table->string('tingkat', 20)->nullable();
            $table->uuid('wali_kelas_guru_id')->nullable();
            $table->timestamps();
            $table->softDeletesTz();

            $table->foreign('lembaga_id')->references('id')->on('lembaga')->restrictOnDelete();
            $table->foreign(['lembaga_id', 'tahun_ajaran_id'])
                ->references(['lembaga_id', 'id'])
                ->on('tahun_ajaran')
                ->restrictOnDelete();
            $table->foreign(['lembaga_id', 'wali_kelas_guru_id'])
                ->references(['lembaga_id', 'id'])
                ->on('guru')
                ->restrictOnDelete();

            $table->unique(['lembaga_id', 'id']);
            $table->unique(['lembaga_id', 'tahun_ajaran_id', 'id']);
            $table->unique(['lembaga_id', 'tahun_ajaran_id', 'nama']);
            $table->index(['lembaga_id', 'updated_at', 'id']);
            $table->index(['lembaga_id', 'deleted_at', 'id']);
        });

        Schema::create('siswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lembaga_id');
            $table->string('nis', 40)->nullable();
            $table->string('nisn', 20)->nullable();
            $table->string('nama', 150);
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->uuid('kelas_id')->nullable();
            $table->uuid('tahun_ajaran_id')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->string('nama_wali', 150)->nullable();
            $table->string('telepon_wali', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletesTz();

            $table->foreign('lembaga_id')->references('id')->on('lembaga')->restrictOnDelete();
            $table->foreign(['lembaga_id', 'tahun_ajaran_id'])
                ->references(['lembaga_id', 'id'])
                ->on('tahun_ajaran')
                ->restrictOnDelete();
            $table->foreign(['lembaga_id', 'tahun_ajaran_id', 'kelas_id'])
                ->references(['lembaga_id', 'tahun_ajaran_id', 'id'])
                ->on('kelas')
                ->restrictOnDelete();

            $table->unique(['lembaga_id', 'id']);
            $table->index(['lembaga_id', 'updated_at', 'id']);
            $table->index(['lembaga_id', 'deleted_at', 'id']);
            $table->index(['lembaga_id', 'nama']);
        });

        Schema::create('karyawan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lembaga_id');
            $table->string('nik_pegawai', 40)->nullable();
            $table->string('nama', 150);
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('jabatan', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletesTz();

            $table->foreign('lembaga_id')->references('id')->on('lembaga')->restrictOnDelete();
            $table->unique(['lembaga_id', 'id']);
            $table->index(['lembaga_id', 'updated_at', 'id']);
            $table->index(['lembaga_id', 'deleted_at', 'id']);
            $table->index(['lembaga_id', 'nama']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('lembaga_id')->nullable();
            $table->string('event', 80);
            $table->enum('result', ['success', 'failed', 'blocked']);
            $table->string('subject_type', 80)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('api_key_prefix', 16)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('lembaga_id')->references('id')->on('lembaga')->nullOnDelete();
            $table->index(['lembaga_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index('request_id');
            $table->index('api_key_prefix');
        });

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('karyawan');
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('guru');
        Schema::dropIfExists('tahun_ajaran');
        Schema::dropIfExists('api_clients');
    }

    private function addPostgresConstraints(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('siswa', function (Blueprint $table) {
                $table->unique(['lembaga_id', 'nis']);
                $table->unique(['lembaga_id', 'nisn']);
            });

            return;
        }

        DB::statement("ALTER TABLE tahun_ajaran ADD CONSTRAINT tahun_ajaran_tanggal_check CHECK (tanggal_selesai > tanggal_mulai)");
        DB::statement("CREATE UNIQUE INDEX tahun_ajaran_one_active_per_lembaga ON tahun_ajaran (lembaga_id) WHERE is_aktif = true AND deleted_at IS NULL");

        DB::statement("ALTER TABLE siswa ADD CONSTRAINT siswa_kelas_tahun_check CHECK (kelas_id IS NULL OR tahun_ajaran_id IS NOT NULL)");
        DB::statement("CREATE UNIQUE INDEX siswa_lembaga_nis_unique ON siswa (lembaga_id, nis) WHERE nis IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX siswa_lembaga_nisn_unique ON siswa (lembaga_id, nisn) WHERE nisn IS NOT NULL");
    }
};
