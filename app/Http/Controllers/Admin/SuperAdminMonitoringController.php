<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Support\Master\SiswaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SuperAdminMonitoringController extends Controller
{
    public function guru(Request $request): View
    {
        $this->superAdmin();

        $filters = $this->filters($request);
        $years = Guru::query()
            ->whereNotNull('tahun_masuk')
            ->distinct()
            ->orderByDesc('tahun_masuk')
            ->pluck('tahun_masuk');

        $rows = Guru::query()
            ->with('lembaga')
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->when($filters['tahun'] !== '', fn (Builder $query) => $query->where('tahun_masuk', (int) $filters['tahun']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->search($query, $filters['q'], ['nama', 'niy', 'nuptk']))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monitoring.index', [
            'resource' => 'guru',
            'title' => 'Monitoring Guru',
            'description' => 'Pantau data guru lintas lembaga secara read-only.',
            'rows' => $rows,
            'lembagas' => $this->lembagas(),
            'tahunAjarans' => collect(),
            'years' => $years,
            'filters' => $filters,
            'resetRoute' => route('admin.monitoring.guru'),
        ]);
    }

    public function siswa(Request $request): View
    {
        $this->superAdmin();

        $filters = $this->filters($request);
        if ($filters['status_siswa'] !== '' && ! in_array($filters['status_siswa'], SiswaStatus::ALL, true)) {
            $filters['status_siswa'] = '';
        }

        $rows = Siswa::query()
            ->with(['lembaga', 'tahunAjaran', 'kelas'])
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->when($filters['tahun_ajaran_id'] !== '', fn (Builder $query) => $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']))
            ->when($filters['status_siswa'] !== '', fn (Builder $query) => $query->where('status_siswa', $filters['status_siswa']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->search($query, $filters['q'], ['nama', 'nis', 'nisn']))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monitoring.index', [
            'resource' => 'siswa',
            'title' => 'Monitoring Siswa',
            'description' => 'Pantau data siswa lintas lembaga dengan filter lembaga dan tahun ajaran.',
            'rows' => $rows,
            'lembagas' => $this->lembagas(),
            'tahunAjarans' => $this->tahunAjarans(),
            'years' => collect(),
            'filters' => $filters,
            'resetRoute' => route('admin.monitoring.siswa'),
        ]);
    }

    public function karyawan(Request $request): View
    {
        $this->superAdmin();

        $filters = $this->filters($request);
        $years = Karyawan::query()
            ->whereNotNull('tahun_masuk')
            ->distinct()
            ->orderByDesc('tahun_masuk')
            ->pluck('tahun_masuk');

        $rows = Karyawan::query()
            ->with('lembaga')
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->when($filters['tahun'] !== '', fn (Builder $query) => $query->where('tahun_masuk', (int) $filters['tahun']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->search($query, $filters['q'], ['nama', 'nik_pegawai', 'jabatan']))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monitoring.index', [
            'resource' => 'karyawan',
            'title' => 'Monitoring Karyawan',
            'description' => 'Pantau data karyawan lintas lembaga secara read-only.',
            'rows' => $rows,
            'lembagas' => $this->lembagas(),
            'tahunAjarans' => collect(),
            'years' => $years,
            'filters' => $filters,
            'resetRoute' => route('admin.monitoring.karyawan'),
        ]);
    }

    private function superAdmin(): void
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);
    }

    /**
     * @return array{q: string, lembaga_id: string, tahun_ajaran_id: string, tahun: string, status: string, status_siswa: string}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['aktif', 'nonaktif'], true)) {
            $status = '';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'lembaga_id' => (string) $request->query('lembaga_id', ''),
            'tahun_ajaran_id' => (string) $request->query('tahun_ajaran_id', ''),
            'tahun' => (string) $request->query('tahun', ''),
            'status' => $status,
            'status_siswa' => (string) $request->query('status_siswa', ''),
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    private function search(Builder $query, string $term, array $columns): void
    {
        $like = '%'.$term.'%';
        $query->where(function (Builder $inner) use ($columns, $like): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $inner->{$method}("lower({$column}) like lower(?)", [$like]);
            }
        });
    }

    /**
     * @return Collection<int, Lembaga>
     */
    private function lembagas(): Collection
    {
        return Lembaga::query()->orderBy('nama')->get();
    }

    /**
     * @return Collection<int, TahunAjaran>
     */
    private function tahunAjarans(): Collection
    {
        return TahunAjaran::query()
            ->with('lembaga')
            ->orderByDesc('nama')
            ->orderBy('lembaga_id')
            ->get();
    }
}
