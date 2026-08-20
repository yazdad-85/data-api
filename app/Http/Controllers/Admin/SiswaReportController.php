<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiswaReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdmin() || $user?->isAdminLembaga(), 403);

        $filters = $this->filters($request);
        if (! $user->isSuperAdmin()) {
            $filters['lembaga_id'] = '';
        }

        $rows = $this->query($filters)
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $lembagas = $user->isSuperAdmin()
            ? Lembaga::query()->orderBy('nama')->get()
            : collect();

        $tahunAjarans = TahunAjaran::query()
            ->with('lembaga')
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->orderByDesc('nama')
            ->get();

        $kelasList = Kelas::query()
            ->with(['lembaga', 'tahunAjaran'])
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->orderBy('nama')
            ->get();

        return view('admin.siswa.report', [
            'rows' => $rows,
            'summary' => $this->summary($filters),
            'filters' => $filters,
            'lembagas' => $lembagas,
            'tahunAjarans' => $tahunAjarans,
            'kelasList' => $kelasList,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    /**
     * @return array{q: string, lembaga_id: string, tahun_ajaran_id: string, kelas_id: string, status_siswa: string}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status_siswa', '');
        if ($status !== '' && ! in_array($status, SiswaStatus::ALL, true)) {
            $status = '';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'lembaga_id' => (string) $request->query('lembaga_id', ''),
            'tahun_ajaran_id' => (string) $request->query('tahun_ajaran_id', ''),
            'kelas_id' => (string) $request->query('kelas_id', ''),
            'status_siswa' => $status,
        ];
    }

    /**
     * @param  array{q: string, lembaga_id: string, tahun_ajaran_id: string, kelas_id: string, status_siswa: string}  $filters
     */
    private function query(array $filters, bool $includeStatus = true): Builder
    {
        return Siswa::query()
            ->with([
                'lembaga',
                'kelas',
                'tahunAjaran',
                'penempatans' => fn ($query) => $query
                    ->with(['kelas', 'tahunAjaran'])
                    ->orderByDesc('mulai_at')
                    ->orderByDesc('created_at'),
            ])
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->when($filters['tahun_ajaran_id'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('tahun_ajaran_id', $filters['tahun_ajaran_id'])
                        ->orWhereHas('penempatans', fn (Builder $placement) => $placement->where('tahun_ajaran_id', $filters['tahun_ajaran_id']));
                });
            })
            ->when($filters['kelas_id'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('kelas_id', $filters['kelas_id'])
                        ->orWhereHas('penempatans', fn (Builder $placement) => $placement->where('kelas_id', $filters['kelas_id']));
                });
            })
            ->when($includeStatus && $filters['status_siswa'] !== '', fn (Builder $query) => $this->applyStatusFilter($query, $filters['status_siswa']))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->search($query, $filters['q']));
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === SiswaStatus::MUTASI_MASUK) {
            $query->where(function (Builder $inner): void {
                $inner->where('status_siswa', SiswaStatus::MUTASI_MASUK)
                    ->orWhereHas('penempatans', fn (Builder $placement) => $placement->where('jenis', PenempatanJenis::MUTASI_MASUK));
            });

            return;
        }

        $query->where('status_siswa', $status);
    }

    private function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';
        $query->where(function (Builder $inner) use ($like): void {
            $inner->whereRaw('lower(nama) like lower(?)', [$like])
                ->orWhereRaw('lower(nis) like lower(?)', [$like])
                ->orWhereRaw('lower(nisn) like lower(?)', [$like])
                ->orWhereRaw('lower(nama_ayah) like lower(?)', [$like])
                ->orWhereRaw('lower(nama_ibu) like lower(?)', [$like]);
        });
    }

    /**
     * @param  array{q: string, lembaga_id: string, tahun_ajaran_id: string, kelas_id: string, status_siswa: string}  $filters
     * @return array{total: int, aktif: int, calon: int, mutasi_masuk: int, mutasi_keluar: int, lulus: int}
     */
    private function summary(array $filters): array
    {
        $base = $this->query($filters, includeStatus: false);

        return [
            'total' => (clone $base)->count(),
            'aktif' => (clone $base)->where('status_siswa', SiswaStatus::AKTIF)->count(),
            'calon' => (clone $base)->where('status_siswa', SiswaStatus::CALON)->count(),
            'mutasi_masuk' => (clone $base)->where(function (Builder $query): void {
                $this->applyStatusFilter($query, SiswaStatus::MUTASI_MASUK);
            })->count(),
            'mutasi_keluar' => (clone $base)->where('status_siswa', SiswaStatus::MUTASI_KELUAR)->count(),
            'lulus' => (clone $base)->where('status_siswa', SiswaStatus::LULUS)->count(),
        ];
    }
}
