<?php

namespace App\Services\Siswa;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SiswaPenempatan;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SiswaLifecycleService
{
    /**
     * @param  array{alasan?: ?string, asal?: ?string, tujuan?: ?string, status_at?: CarbonInterface|string|null}  $meta
     */
    public function setStatus(Siswa $siswa, string $to, array $meta = []): Siswa
    {
        return DB::transaction(function () use ($siswa, $to, $meta) {
            $siswa = $this->lockSiswa($siswa);

            $this->assertTransition($siswa->status_siswa, $to);

            $this->applyStatusFlags($siswa, $to, $this->resolveStatusAt($meta));
            $this->applyMeta($siswa, $meta);
            $siswa->save();

            return $siswa->refresh();
        });
    }

    public function tempatkan(Siswa $siswa, Kelas $kelas, ?CarbonInterface $mulai = null, string $jenis = PenempatanJenis::AWAL): Siswa
    {
        return DB::transaction(function () use ($siswa, $kelas, $mulai, $jenis) {
            $siswa = $this->lockSiswa($siswa);
            $this->assertSameLembaga($siswa, $kelas);

            $from = $siswa->status_siswa;
            if (! in_array($from, [SiswaStatus::CALON, SiswaStatus::MUTASI_MASUK], true)) {
                throw new InvalidArgumentException(
                    "Siswa dengan status \"{$from}\" tidak dapat ditempatkan langsung. Gunakan pindah kelas untuk siswa aktif."
                );
            }

            $this->assertTransition($from, SiswaStatus::AKTIF);

            $jenisEfektif = $jenis === PenempatanJenis::AWAL && $from === SiswaStatus::MUTASI_MASUK
                ? PenempatanJenis::MUTASI_MASUK
                : $jenis;

            $mulaiEfektif = $mulai ?? Carbon::now();

            $this->closeOpenPenempatan($siswa, $mulaiEfektif);
            $this->openPenempatan($siswa, $kelas, $jenisEfektif, $mulaiEfektif);
            $this->syncSnapshot($siswa, $kelas);
            $this->applyStatusFlags($siswa, SiswaStatus::AKTIF, $mulaiEfektif);
            $siswa->save();

            return $siswa->refresh();
        });
    }

    public function pindahKelas(Siswa $siswa, Kelas $kelasTujuan, ?CarbonInterface $mulai = null): Siswa
    {
        return DB::transaction(function () use ($siswa, $kelasTujuan, $mulai) {
            $siswa = $this->lockSiswa($siswa);
            $this->assertSameLembaga($siswa, $kelasTujuan);

            if ($siswa->status_siswa !== SiswaStatus::AKTIF) {
                throw new InvalidArgumentException('Hanya siswa dengan status "aktif" yang dapat dipindah kelas.');
            }

            $this->assertTransition(SiswaStatus::AKTIF, SiswaStatus::AKTIF);

            $open = $this->findOpenPenempatan($siswa, lock: true);
            if ($open === null) {
                throw new InvalidArgumentException('Siswa belum memiliki penempatan terbuka untuk dipindahkan.');
            }

            $jenis = $open->tahun_ajaran_id === $kelasTujuan->tahun_ajaran_id
                ? PenempatanJenis::PINDAH_KELAS
                : PenempatanJenis::KENAIKAN;

            $mulaiEfektif = $mulai ?? Carbon::now();

            $this->closeOpenPenempatan($siswa, $mulaiEfektif);
            $this->openPenempatan($siswa, $kelasTujuan, $jenis, $mulaiEfektif);
            $this->syncSnapshot($siswa, $kelasTujuan);
            $this->applyStatusFlags($siswa, SiswaStatus::AKTIF, $mulaiEfektif);
            $siswa->save();

            return $siswa->refresh();
        });
    }

    /**
     * @param  array{alasan?: ?string, asal?: ?string, tujuan?: ?string, status_at?: CarbonInterface|string|null}  $meta
     */
    public function mutasiKeluar(Siswa $siswa, array $meta = []): Siswa
    {
        return DB::transaction(function () use ($siswa, $meta) {
            $siswa = $this->lockSiswa($siswa);
            $this->assertTransition($siswa->status_siswa, SiswaStatus::MUTASI_KELUAR);

            $efektifAt = $this->resolveStatusAt($meta) ?? Carbon::now();

            $this->closeOpenPenempatan($siswa, $efektifAt);
            $this->syncSnapshot($siswa, null);
            $this->applyStatusFlags($siswa, SiswaStatus::MUTASI_KELUAR, $efektifAt);
            $this->applyMeta($siswa, $meta);
            $siswa->save();

            return $siswa->refresh();
        });
    }

    /**
     * @param  array{alasan?: ?string, asal?: ?string, tujuan?: ?string, status_at?: CarbonInterface|string|null}  $meta
     */
    public function luluskan(Siswa $siswa, array $meta = []): Siswa
    {
        return DB::transaction(function () use ($siswa, $meta) {
            $siswa = $this->lockSiswa($siswa);
            $this->assertTransition($siswa->status_siswa, SiswaStatus::LULUS);

            $efektifAt = $this->resolveStatusAt($meta) ?? Carbon::now();

            $this->closeOpenPenempatan($siswa, $efektifAt);
            $this->syncSnapshot($siswa, null);
            $this->applyStatusFlags($siswa, SiswaStatus::LULUS, $efektifAt);
            $this->applyMeta($siswa, $meta);
            $siswa->save();

            return $siswa->refresh();
        });
    }

    private function assertTransition(string $from, string $to): void
    {
        if (! in_array($to, SiswaStatus::allowedTransitions($from), true)) {
            throw new InvalidArgumentException(
                "Transisi status siswa dari \"{$from}\" ke \"{$to}\" tidak diizinkan."
            );
        }
    }

    private function assertSameLembaga(Siswa $siswa, Kelas $kelas): void
    {
        if ($siswa->lembaga_id !== $kelas->lembaga_id) {
            throw new InvalidArgumentException('Kelas harus berasal dari lembaga yang sama dengan siswa.');
        }
    }

    /**
     * Mengambil ulang siswa dengan row-level lock agar mutasi lifecycle yang
     * berjalan bersamaan (concurrent) untuk siswa yang sama tidak saling tabrakan.
     */
    private function lockSiswa(Siswa $siswa): Siswa
    {
        $locked = Siswa::withoutGlobalScopes()
            ->where('lembaga_id', $siswa->lembaga_id)
            ->whereKey($siswa->getKey())
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw new InvalidArgumentException('Siswa tidak ditemukan.');
        }

        return $locked;
    }

    private function findOpenPenempatan(Siswa $siswa, bool $lock = false): ?SiswaPenempatan
    {
        $query = SiswaPenempatan::withoutGlobalScopes()
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('siswa_id', $siswa->id)
            ->whereNull('selesai_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Menutup penempatan terbuka milik siswa dengan hanya menandai `selesai_at`.
     * `jenis`/`kelas_id`/`tahun_ajaran_id` pada baris tersebut dipertahankan sebagai
     * jejak historis; snapshot kelas siswa saat ini dikosongkan lewat `syncSnapshot`.
     */
    private function closeOpenPenempatan(Siswa $siswa, CarbonInterface $selesaiAt): void
    {
        $open = $this->findOpenPenempatan($siswa, lock: true);

        if ($open === null) {
            return;
        }

        $open->selesai_at = $selesaiAt->toDateString();
        $open->save();
    }

    private function openPenempatan(Siswa $siswa, ?Kelas $kelas, string $jenis, CarbonInterface $mulaiAt, ?string $keterangan = null): SiswaPenempatan
    {
        return SiswaPenempatan::create([
            'lembaga_id' => $siswa->lembaga_id,
            'siswa_id' => $siswa->id,
            'tahun_ajaran_id' => $kelas?->tahun_ajaran_id,
            'kelas_id' => $kelas?->id,
            'mulai_at' => $mulaiAt->toDateString(),
            'selesai_at' => null,
            'jenis' => $jenis,
            'keterangan' => $keterangan,
        ]);
    }

    private function syncSnapshot(Siswa $siswa, ?Kelas $kelas): void
    {
        $siswa->kelas_id = $kelas?->id;
        $siswa->tahun_ajaran_id = $kelas?->tahun_ajaran_id;
    }

    private function applyStatusFlags(Siswa $siswa, string $status, ?CarbonInterface $statusAt = null): void
    {
        $siswa->status_siswa = $status;
        $siswa->status_at = ($statusAt ?? Carbon::now())->toDateString();

        $isActive = SiswaStatus::isActiveFlag($status);
        if ($status === SiswaStatus::MUTASI_MASUK && $siswa->kelas_id === null) {
            $isActive = false;
        }

        $siswa->is_active = $isActive;
    }

    /**
     * @param  array{alasan?: ?string, asal?: ?string, tujuan?: ?string, status_at?: CarbonInterface|string|null}  $meta
     */
    private function applyMeta(Siswa $siswa, array $meta): void
    {
        if (array_key_exists('alasan', $meta)) {
            $siswa->status_alasan = $meta['alasan'];
        }

        if (array_key_exists('asal', $meta)) {
            $siswa->status_asal = $meta['asal'];
        }

        if (array_key_exists('tujuan', $meta)) {
            $siswa->status_tujuan = $meta['tujuan'];
        }
    }

    /**
     * @param  array{status_at?: CarbonInterface|string|null}  $meta
     */
    private function resolveStatusAt(array $meta): ?CarbonInterface
    {
        $value = $meta['status_at'] ?? null;

        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonInterface ? $value : Carbon::parse((string) $value);
    }
}
