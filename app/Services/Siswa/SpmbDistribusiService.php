<?php

namespace App\Services\Siswa;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SpmbDistribusiService
{
    public function __construct(
        private readonly SiswaLifecycleService $lifecycle,
    ) {}

    /**
     * Menempatkan sekumpulan calon murid (status CALON) ke satu kelas tujuan sekaligus.
     *
     * Bersifat atomik: seluruh siswa_id divalidasi lebih dulu; hanya jika semua valid
     * penempatan dijalankan dalam satu transaksi. Bila terjadi kegagalan, seluruh batch
     * di-rollback dan tidak ada perubahan yang tersimpan.
     *
     * @param  list<string>  $siswaIds
     * @return array{success: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function commit(string $lembagaId, Kelas $kelasTujuan, array $siswaIds, ?CarbonInterface $mulai = null): array
    {
        $eligible = Siswa::withoutGlobalScopes()
            ->where('lembaga_id', $lembagaId)
            ->where('status_siswa', SiswaStatus::CALON)
            ->whereIn('id', $siswaIds)
            ->get()
            ->keyBy('id');

        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];
        /** @var list<Siswa> $plan */
        $plan = [];
        $seen = [];

        foreach ($siswaIds as $index => $siswaId) {
            $rowNumber = $index + 1;
            $siswaId = (string) $siswaId;

            $siswa = $eligible->get($siswaId);
            if ($siswa === null) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Siswa tidak ditemukan atau bukan calon murid.'];

                continue;
            }

            if (isset($seen[$siswaId])) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Siswa muncul lebih dari satu kali dalam batch.'];

                continue;
            }
            $seen[$siswaId] = true;

            $plan[] = $siswa;
        }

        if ($errors !== []) {
            return ['success' => 0, 'failed' => count($siswaIds), 'errors' => $errors];
        }

        try {
            DB::transaction(function () use ($plan, $kelasTujuan, $mulai): void {
                foreach ($plan as $siswa) {
                    $this->lifecycle->tempatkan($siswa, $kelasTujuan, $mulai, PenempatanJenis::AWAL);
                }
            });
        } catch (Throwable) {
            return [
                'success' => 0,
                'failed' => count($siswaIds),
                'errors' => [['row' => 0, 'message' => 'Terjadi kesalahan saat memproses batch. Tidak ada perubahan yang disimpan.']],
            ];
        }

        return ['success' => count($plan), 'failed' => 0, 'errors' => []];
    }
}
