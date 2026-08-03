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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $rows = $this->guruQuery($filters)
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

    public function guruExport(Request $request): StreamedResponse
    {
        $this->superAdmin();

        $rows = $this->guruQuery($this->filters($request))
            ->orderBy('nama')
            ->get();

        return $this->exportResponse('guru', $rows);
    }

    public function siswa(Request $request): View
    {
        $this->superAdmin();

        $filters = $this->filters($request);
        $this->normalizeSiswaFilters($filters);

        $rows = $this->siswaQuery($filters)
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

    public function siswaExport(Request $request): StreamedResponse
    {
        $this->superAdmin();

        $filters = $this->filters($request);
        $this->normalizeSiswaFilters($filters);

        $rows = $this->siswaQuery($filters)
            ->orderBy('nama')
            ->get();

        return $this->exportResponse('siswa', $rows);
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

        $rows = $this->karyawanQuery($filters)
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

    public function karyawanExport(Request $request): StreamedResponse
    {
        $this->superAdmin();

        $rows = $this->karyawanQuery($this->filters($request))
            ->orderBy('nama')
            ->get();

        return $this->exportResponse('karyawan', $rows);
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
     * @param  array{q: string, lembaga_id: string, tahun_ajaran_id: string, tahun: string, status: string, status_siswa: string}  $filters
     */
    private function guruQuery(array $filters): Builder
    {
        return Guru::query()
            ->with('lembaga')
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->when($filters['tahun'] !== '', fn (Builder $query) => $query->where('tahun_masuk', (int) $filters['tahun']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->search($query, $filters['q'], ['nama', 'niy', 'nuptk']));
    }

    /**
     * @param  array{q: string, lembaga_id: string, tahun_ajaran_id: string, tahun: string, status: string, status_siswa: string}  $filters
     */
    private function siswaQuery(array $filters): Builder
    {
        return Siswa::query()
            ->with(['lembaga', 'tahunAjaran', 'kelas'])
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->when($filters['tahun_ajaran_id'] !== '', fn (Builder $query) => $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']))
            ->when($filters['status_siswa'] !== '', fn (Builder $query) => $query->where('status_siswa', $filters['status_siswa']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->search($query, $filters['q'], ['nama', 'nis', 'nisn']));
    }

    /**
     * @param  array{q: string, lembaga_id: string, tahun_ajaran_id: string, tahun: string, status: string, status_siswa: string}  $filters
     */
    private function karyawanQuery(array $filters): Builder
    {
        return Karyawan::query()
            ->with('lembaga')
            ->when($filters['lembaga_id'] !== '', fn (Builder $query) => $query->where('lembaga_id', $filters['lembaga_id']))
            ->when($filters['tahun'] !== '', fn (Builder $query) => $query->where('tahun_masuk', (int) $filters['tahun']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'aktif'))
            ->when($filters['q'] !== '', fn (Builder $query) => $this->search($query, $filters['q'], ['nama', 'nik_pegawai', 'jabatan']));
    }

    /**
     * @param  array{q: string, lembaga_id: string, tahun_ajaran_id: string, tahun: string, status: string, status_siswa: string}  $filters
     */
    private function normalizeSiswaFilters(array &$filters): void
    {
        if ($filters['status_siswa'] !== '' && ! in_array($filters['status_siswa'], SiswaStatus::ALL, true)) {
            $filters['status_siswa'] = '';
        }
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
     * @param  Collection<int, mixed>  $rows
     */
    private function exportResponse(string $resource, Collection $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ucfirst($resource));

        $headers = $this->exportHeaders($resource);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$this->columnLetter(count($headers)).'1')->getFont()->setBold(true);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($this->exportRow($resource, $row), null, 'A'.$rowNumber);
            $rowNumber++;
        }

        $sheet->freezePane('A2');
        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension($this->columnLetter($columnIndex))->setAutoSize(true);
        }

        $filename = 'monitoring-'.$resource.'-'.now()->format('Ymd-His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @return list<string>
     */
    private function exportHeaders(string $resource): array
    {
        return match ($resource) {
            'guru' => ['Nama', 'Lembaga', 'NIY', 'NUPTK', 'Tahun Masuk', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Email', 'Telepon', 'Alamat', 'Status Kepegawaian', 'Status Aktif'],
            'siswa' => ['Nama', 'Lembaga', 'NIS', 'NISN', 'Tahun Ajaran', 'Kelas', 'Status Siswa', 'Status Aktif', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Email', 'Telepon', 'Alamat', 'Nama Wali', 'Telepon Wali', 'Asal', 'Tujuan', 'Alasan Status'],
            'karyawan' => ['Nama', 'Lembaga', 'NIK Pegawai', 'Tahun Masuk', 'Jenis Kelamin', 'Jabatan', 'Email', 'Telepon', 'Alamat', 'Status Aktif'],
            default => [],
        };
    }

    /**
     * @return list<mixed>
     */
    private function exportRow(string $resource, mixed $row): array
    {
        return match ($resource) {
            'guru' => [
                $row->nama,
                $row->lembaga?->nama,
                $row->niy,
                $row->nuptk,
                $row->tahun_masuk,
                $row->jenis_kelamin,
                $row->tempat_lahir,
                $row->tanggal_lahir?->format('Y-m-d'),
                $row->email,
                $row->telepon,
                $row->alamat,
                $row->status_kepegawaian,
                $row->is_active ? 'Aktif' : 'Nonaktif',
            ],
            'siswa' => [
                $row->nama,
                $row->lembaga?->nama,
                $row->nis,
                $row->nisn,
                $row->tahunAjaran?->nama,
                $row->kelas?->nama,
                SiswaStatus::label($row->status_siswa),
                $row->is_active ? 'Aktif' : 'Nonaktif',
                $row->jenis_kelamin,
                $row->tempat_lahir,
                $row->tanggal_lahir?->format('Y-m-d'),
                $row->email,
                $row->telepon,
                $row->alamat,
                $row->nama_wali,
                $row->telepon_wali,
                $row->status_asal,
                $row->status_tujuan,
                $row->status_alasan,
            ],
            'karyawan' => [
                $row->nama,
                $row->lembaga?->nama,
                $row->nik_pegawai,
                $row->tahun_masuk,
                $row->jenis_kelamin,
                $row->jabatan,
                $row->email,
                $row->telepon,
                $row->alamat,
                $row->is_active ? 'Aktif' : 'Nonaktif',
            ],
            default => [],
        };
    }

    private function columnLetter(int $columnIndex): string
    {
        $letter = '';
        while ($columnIndex > 0) {
            $remainder = ($columnIndex - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $columnIndex = intdiv($columnIndex - 1, 26);
        }

        return $letter;
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
