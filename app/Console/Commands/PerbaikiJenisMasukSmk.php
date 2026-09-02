<?php

namespace App\Console\Commands;

use App\Models\SiswaPenempatan;
use App\Services\AuditLogger;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PerbaikiJenisMasukSmk extends Command
{
    protected $signature = 'siswa:perbaiki-jenis-masuk-smk
        {--apply : Terapkan perubahan. Tanpa opsi ini, command hanya menampilkan pratinjau (dry-run).}';

    protected $description = 'Koreksi data siswa SMK kelas X & XI yang salah diupload sebagai mutasi masuk, seharusnya siswa baru.';

    public function handle(AuditLogger $auditLogger): int
    {
        $apply = (bool) $this->option('apply');

        // Command ini berjalan tanpa user terautentikasi, jadi global scope
        // BelongsToLembaga (yang butuh Auth::user()) harus dilepas eksplisit
        // di query utama maupun di setiap whereHas relasi terkait.
        $query = SiswaPenempatan::query()
            ->withoutGlobalScope('lembaga')
            ->where('jenis', PenempatanJenis::MUTASI_MASUK)
            ->whereNull('selesai_at')
            ->whereHas('lembaga', fn ($q) => $q->where('nama', 'like', '%SMK%'))
            ->whereHas('kelas', fn ($q) => $q->withoutGlobalScope('lembaga')->whereIn('tingkat', ['10', '11']))
            ->whereHas('siswa', fn ($q) => $q->withoutGlobalScope('lembaga')->where('status_siswa', SiswaStatus::AKTIF));

        $rows = $query->with([
            'siswa' => fn ($q) => $q->withoutGlobalScope('lembaga')->select(['id', 'nama', 'nis', 'kelas_id', 'lembaga_id']),
            'kelas' => fn ($q) => $q->withoutGlobalScope('lembaga')->select(['id', 'nama', 'tingkat', 'lembaga_id']),
            'lembaga:id,nama,jenis',
        ])->get();

        if ($rows->isEmpty()) {
            $this->info('Tidak ada penempatan mutasi_masuk yang cocok (SMK, kelas X/XI, siswa aktif).');

            return self::SUCCESS;
        }

        $this->table(
            ['Siswa', 'NIS', 'Lembaga', 'Kelas', 'Penempatan ID'],
            $rows->map(fn (SiswaPenempatan $p) => [
                $p->siswa->nama ?? '-',
                $p->siswa->nis ?? '-',
                $p->lembaga->nama ?? '-',
                $p->kelas->nama.' ('.$p->kelas->tingkat.')',
                $p->id,
            ])
        );

        $this->line(count($rows).' baris penempatan akan diubah dari "mutasi_masuk" menjadi "'.PenempatanJenis::AWAL.'".');

        if (! $apply) {
            $this->warn('Dry-run. Jalankan ulang dengan --apply untuk menerapkan perubahan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows, $auditLogger) {
            foreach ($rows as $penempatan) {
                $penempatan->jenis = PenempatanJenis::AWAL;
                $penempatan->save();

                $auditLogger->record('siswa_penempatan.koreksi_jenis_masuk', 'success', [
                    'dari' => PenempatanJenis::MUTASI_MASUK,
                    'ke' => PenempatanJenis::AWAL,
                    'siswa_nama' => $penempatan->siswa->nama ?? null,
                    'siswa_nis' => $penempatan->siswa->nis ?? null,
                ], subject: $penempatan, lembagaId: $penempatan->lembaga_id);
            }
        });

        $this->info(count($rows).' baris penempatan berhasil diperbaiki menjadi siswa baru.');

        return self::SUCCESS;
    }
}
