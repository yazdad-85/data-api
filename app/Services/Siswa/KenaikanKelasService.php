<?php

namespace App\Services\Siswa;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Support\Master\SiswaStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class KenaikanKelasService
{
    public function __construct(
        private readonly SiswaLifecycleService $lifecycle,
    ) {}

    /**
     * Memproses kenaikan/mutasi batch untuk siswa aktif di kelas asal.
     *
     * Bersifat atomik: seluruh baris divalidasi lebih dulu; hanya jika semua baris
     * valid mutasi dijalankan dalam satu transaksi. Bila terjadi kegagalan saat
     * mutasi, seluruh batch di-rollback dan tidak ada perubahan yang tersimpan.
     *
     * @param  list<array{siswa_id: string, aksi: 'naik'|'tinggal'|'lulus'|'mutasi_keluar', kelas_tujuan_id?: ?string, meta?: array<string, mixed>}>  $rows
     * @return array{success: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function commit(Kelas $kelasAsal, ?TahunAjaran $tahunTujuan, ?Kelas $kelasTujuanDefault, array $rows, CarbonInterface $efektifAt): array
    {
        $eligible = $this->eligibleSiswa($kelasAsal);
        $kelasCache = $this->kelasCache($kelasAsal, $kelasTujuanDefault);

        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];
        /** @var list<array{row: int, siswa: Siswa, aksi: string, kelasTujuan: ?Kelas, meta: array<string, mixed>}> $plan */
        $plan = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            $siswaId = isset($row['siswa_id']) ? (string) $row['siswa_id'] : '';
            $aksi = isset($row['aksi']) ? (string) $row['aksi'] : '';

            $siswa = $siswaId !== '' ? $eligible->get($siswaId) : null;
            if ($siswa === null) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Siswa tidak ditemukan atau tidak berstatus aktif di kelas asal.'];

                continue;
            }

            if (isset($seen[$siswaId])) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Siswa muncul lebih dari satu kali dalam batch.'];

                continue;
            }
            $seen[$siswaId] = true;

            if (! in_array($aksi, ['naik', 'tinggal', 'lulus', 'mutasi_keluar'], true)) {
                $errors[] = ['row' => $rowNumber, 'message' => "Aksi \"{$aksi}\" tidak dikenal."];

                continue;
            }

            $kelasTujuanId = isset($row['kelas_tujuan_id']) && $row['kelas_tujuan_id'] !== ''
                ? (string) $row['kelas_tujuan_id']
                : null;

            $meta = isset($row['meta']) && is_array($row['meta']) ? $row['meta'] : [];

            $kelasTujuan = null;

            if ($aksi === 'naik') {
                $targetId = $kelasTujuanId ?? $kelasTujuanDefault?->id;
                if ($targetId === null) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Kelas tujuan wajib dipilih untuk aksi naik kelas.'];

                    continue;
                }

                $kelasTujuan = $this->resolveKelas($kelasCache, $kelasAsal, $targetId);
                if ($kelasTujuan === null) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Kelas tujuan tidak ditemukan pada lembaga ini.'];

                    continue;
                }
            }

            if ($aksi === 'tinggal' && $kelasTujuanId !== null && $kelasTujuanId !== $kelasAsal->id) {
                $kelasTujuan = $this->resolveKelas($kelasCache, $kelasAsal, $kelasTujuanId);
                if ($kelasTujuan === null) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Kelas tujuan tidak ditemukan pada lembaga ini.'];

                    continue;
                }

                if ($kelasTujuan->tahun_ajaran_id !== $kelasAsal->tahun_ajaran_id) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Untuk aksi tinggal, kelas tujuan harus berada di tahun ajaran yang sama.'];

                    continue;
                }
            }

            $plan[] = [
                'row' => $rowNumber,
                'siswa' => $siswa,
                'aksi' => $aksi,
                'kelasTujuan' => $kelasTujuan,
                'meta' => $meta,
            ];
        }

        if ($errors !== []) {
            return ['success' => 0, 'failed' => count($rows), 'errors' => $errors];
        }

        try {
            DB::transaction(function () use ($plan, $efektifAt): void {
                foreach ($plan as $op) {
                    $this->apply($op['siswa'], $op['aksi'], $op['kelasTujuan'], $op['meta'], $efektifAt);
                }
            });
        } catch (Throwable $exception) {
            $message = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Terjadi kesalahan saat memproses batch. Tidak ada perubahan yang disimpan.';

            return [
                'success' => 0,
                'failed' => count($rows),
                'errors' => [['row' => 0, 'message' => $message]],
            ];
        }

        return ['success' => count($plan), 'failed' => 0, 'errors' => []];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function apply(Siswa $siswa, string $aksi, ?Kelas $kelasTujuan, array $meta, CarbonInterface $efektifAt): void
    {
        switch ($aksi) {
            case 'naik':
                // $kelasTujuan dipastikan tidak null pada tahap validasi.
                $this->lifecycle->pindahKelas($siswa, $kelasTujuan, $efektifAt);
                break;

            case 'tinggal':
                if ($kelasTujuan !== null) {
                    $this->lifecycle->pindahKelas($siswa, $kelasTujuan, $efektifAt);
                }
                // Tanpa kelas tujuan: biarkan siswa di kelas asal (no-op).
                break;

            case 'lulus':
                $this->lifecycle->luluskan($siswa, $this->metaWithStatusAt($meta, $efektifAt));
                break;

            case 'mutasi_keluar':
                $this->lifecycle->mutasiKeluar($siswa, $this->metaWithStatusAt($meta, $efektifAt));
                break;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function metaWithStatusAt(array $meta, CarbonInterface $efektifAt): array
    {
        if (! array_key_exists('status_at', $meta) || $meta['status_at'] === null || $meta['status_at'] === '') {
            $meta['status_at'] = $efektifAt;
        }

        return $meta;
    }

    /**
     * @return \Illuminate\Support\Collection<string, Siswa>
     */
    private function eligibleSiswa(Kelas $kelasAsal): \Illuminate\Support\Collection
    {
        return Siswa::withoutGlobalScopes()
            ->where('lembaga_id', $kelasAsal->lembaga_id)
            ->where('kelas_id', $kelasAsal->id)
            ->where('status_siswa', SiswaStatus::AKTIF)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array<string, Kelas>
     */
    private function kelasCache(Kelas $kelasAsal, ?Kelas $kelasTujuanDefault): array
    {
        $cache = [$kelasAsal->id => $kelasAsal];
        if ($kelasTujuanDefault !== null) {
            $cache[$kelasTujuanDefault->id] = $kelasTujuanDefault;
        }

        return $cache;
    }

    /**
     * @param  array<string, Kelas>  $cache
     */
    private function resolveKelas(array &$cache, Kelas $kelasAsal, string $kelasId): ?Kelas
    {
        if (isset($cache[$kelasId])) {
            return $cache[$kelasId];
        }

        $kelas = Kelas::withoutGlobalScopes()
            ->where('lembaga_id', $kelasAsal->lembaga_id)
            ->whereKey($kelasId)
            ->first();

        if ($kelas !== null) {
            $cache[$kelasId] = $kelas;
        }

        return $kelas;
    }
}
