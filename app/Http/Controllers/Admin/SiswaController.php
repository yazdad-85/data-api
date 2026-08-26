<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiswaLifecycleRequest;
use App\Http\Requests\Admin\StoreSiswaRequest;
use App\Http\Requests\Admin\UpdateSiswaRequest;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\AuditLogger;
use App\Services\Siswa\SiswaLifecycleService;
use App\Support\Master\PenempatanJenis;
use App\Support\Master\SiswaStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use InvalidArgumentException;

class SiswaController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SiswaLifecycleService $lifecycle,
    ) {}

    public function index(Request $request): View
    {
        $this->adminLembaga();

        $q = trim((string) $request->query('q', ''));
        $kelasId = $request->query('kelas_id');
        $tahunAjaranId = $request->query('tahun_ajaran_id');
        $statusSiswa = $request->query('status_siswa');
        if (! is_string($statusSiswa) || ! in_array($statusSiswa, SiswaStatus::ALL, true)) {
            $statusSiswa = '';
        }

        $siswas = Siswa::query()
            ->with(['kelas', 'tahunAjaran'])
            ->when(is_string($kelasId) && $kelasId !== '', fn ($query) => $query->where('kelas_id', $kelasId))
            ->when(is_string($tahunAjaranId) && $tahunAjaranId !== '', fn ($query) => $query->where('tahun_ajaran_id', $tahunAjaranId))
            ->when($statusSiswa !== '', fn ($query) => $query->where('status_siswa', $statusSiswa))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    if ($inner->getConnection()->getDriverName() === 'pgsql') {
                        $inner->where('nama', 'ilike', $like)
                            ->orWhere('nis', 'ilike', $like)
                            ->orWhere('nisn', 'ilike', $like)
                            ->orWhere('nama_ayah', 'ilike', $like)
                            ->orWhere('nama_ibu', 'ilike', $like);
                    } else {
                        $inner->whereRaw('lower(nama) like lower(?)', [$like])
                            ->orWhereRaw('lower(nis) like lower(?)', [$like])
                            ->orWhereRaw('lower(nisn) like lower(?)', [$like])
                            ->orWhereRaw('lower(nama_ayah) like lower(?)', [$like])
                            ->orWhereRaw('lower(nama_ibu) like lower(?)', [$like]);
                    }
                });
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        $kelasList = Kelas::query()
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        return view('admin.siswa.index', compact('siswas', 'q', 'kelasId', 'tahunAjaranId', 'statusSiswa', 'kelasList', 'tahunAjarans'));
    }

    public function create(): View
    {
        $this->adminLembaga();

        $kelasList = Kelas::query()
            ->with('tahunAjaran')
            ->orderBy('nama')
            ->get();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->get();

        return view('admin.siswa.create', compact('kelasList', 'tahunAjarans'));
    }

    public function store(StoreSiswaRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();

        $validated = $request->validated();
        $kelasId = $validated['kelas_id'] ?? null;
        $tahunAjaranId = $validated['tahun_ajaran_id'] ?? null;
        $jenisMasuk = $validated['jenis_masuk'] ?? 'siswa_baru';
        $asalLembaga = $validated['asal_lembaga'] ?? null;
        $diterimaTanggal = $validated['diterima_tanggal'] ?? null;
        unset($validated['kelas_id'], $validated['tahun_ajaran_id'], $validated['jenis_masuk'], $validated['asal_lembaga'], $validated['diterima_tanggal']);

        // Penempatan + status aktif ditangani SiswaLifecycleService agar tidak
        // ada baris penempatan terbuka ganda. Kalau belum ada kelas, tahun_ajaran_id
        // tetap disimpan langsung supaya calon murid bisa ditandai per angkatan (SPMB).
        $siswa = Siswa::query()->create([
            ...$validated,
            'lembaga_id' => $user->lembaga_id,
            'tahun_ajaran_id' => $kelasId === null ? $tahunAjaranId : null,
            'status_siswa' => $jenisMasuk === 'mutasi_masuk' ? SiswaStatus::MUTASI_MASUK : SiswaStatus::CALON,
            'status_at' => $jenisMasuk === 'mutasi_masuk' ? $diterimaTanggal : null,
            'status_asal' => $jenisMasuk === 'mutasi_masuk' ? $asalLembaga : null,
            'is_active' => false,
        ]);

        if (is_string($kelasId) && $kelasId !== '') {
            $kelas = Kelas::query()
                ->where('lembaga_id', $user->lembaga_id)
                ->findOrFail($kelasId);

            $siswa = $this->lifecycle->tempatkan(
                $siswa,
                $kelas,
                $jenisMasuk === 'mutasi_masuk' && $diterimaTanggal !== null ? Carbon::parse((string) $diterimaTanggal) : null,
                $jenisMasuk === 'mutasi_masuk' ? PenempatanJenis::MUTASI_MASUK : PenempatanJenis::AWAL,
            );
        }

        $this->auditLogger->record('siswa.create', 'success', [
            'nama' => $siswa->nama,
            'status_siswa' => $siswa->status_siswa,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} berhasil dibuat.");
    }

    public function show(Request $request, Siswa $siswa): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $siswa->load(['kelas', 'tahunAjaran']);

        $penempatans = $siswa->penempatans()
            ->with(['kelas', 'tahunAjaran'])
            ->orderByDesc('mulai_at')
            ->orderByDesc('created_at')
            ->get();

        $current = $siswa->status_siswa;
        $allowed = SiswaStatus::allowedTransitions($current);

        $canTempatkan = in_array($current, [SiswaStatus::CALON, SiswaStatus::MUTASI_MASUK], true);
        $canPindah = $current === SiswaStatus::AKTIF;
        $canMutasiKeluar = in_array(SiswaStatus::MUTASI_KELUAR, $allowed, true);
        $canLulus = in_array(SiswaStatus::LULUS, $allowed, true);
        $statusTargets = array_values(array_intersect($allowed, [SiswaStatus::CALON, SiswaStatus::MUTASI_MASUK]));

        $needsKelas = $canTempatkan || $canPindah;
        $kelasList = $needsKelas
            ? Kelas::query()->with('tahunAjaran')->orderBy('nama')->get()
            : collect();

        $this->auditLogger->record('master.view', 'success', [
            'resource' => 'siswa',
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return view('admin.siswa.show', compact(
            'siswa',
            'penempatans',
            'kelasList',
            'canTempatkan',
            'canPindah',
            'canMutasiKeluar',
            'canLulus',
            'statusTargets',
        ));
    }

    public function edit(Siswa $siswa): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        return view('admin.siswa.edit', compact('siswa'));
    }

    public function update(UpdateSiswaRequest $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        // Belt-and-suspenders: kelas_id/tahun_ajaran_id must never change via
        // ordinary edit, even if a caller bypasses UpdateSiswaRequest's rules.
        // Use SiswaLifecycleService (tempatkan/pindah) to move a siswa's kelas.
        $validated = $request->validated();
        unset($validated['kelas_id'], $validated['tahun_ajaran_id']);

        $siswa->update($validated);

        $this->auditLogger->record('siswa.update', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} berhasil diperbarui.");
    }

    public function activate(Request $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        if ($redirect = $this->rejectOperationalToggleUnlessAktif($siswa)) {
            return $redirect;
        }

        $siswa->update(['is_active' => true]);

        $this->auditLogger->record('siswa.activate', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} diaktifkan.");
    }

    public function deactivate(Request $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        if ($redirect = $this->rejectOperationalToggleUnlessAktif($siswa)) {
            return $redirect;
        }

        $siswa->update(['is_active' => false]);

        $this->auditLogger->record('siswa.deactivate', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} dinonaktifkan.");
    }

    public function destroy(Request $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $siswa->delete();

        $this->auditLogger->record('siswa.delete', 'success', [
            'nama' => $siswa->nama,
        ], subject: $siswa, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.siswa.index')
            ->with('status', "Siswa {$siswa->nama} dihapus.");
    }

    public function tempatkan(SiswaLifecycleRequest $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $kelas = Kelas::query()
            ->where('lembaga_id', $user->lembaga_id)
            ->findOrFail($request->validated('kelas_id'));

        return $this->runLifecycle(
            $request,
            $siswa,
            'tempatkan',
            fn () => $this->lifecycle->tempatkan($siswa, $kelas, $request->filled('mulai_at') ? Carbon::parse($request->validated('mulai_at')) : null),
            "Siswa {$siswa->nama} ditempatkan di kelas {$kelas->nama}.",
        );
    }

    public function pindahKelas(SiswaLifecycleRequest $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $kelas = Kelas::query()
            ->where('lembaga_id', $user->lembaga_id)
            ->findOrFail($request->validated('kelas_id'));

        return $this->runLifecycle(
            $request,
            $siswa,
            'pindah',
            fn () => $this->lifecycle->pindahKelas($siswa, $kelas, $request->filled('mulai_at') ? Carbon::parse($request->validated('mulai_at')) : null),
            "Siswa {$siswa->nama} dipindah ke kelas {$kelas->nama}.",
        );
    }

    public function mutasiKeluar(SiswaLifecycleRequest $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        return $this->runLifecycle(
            $request,
            $siswa,
            'mutasi_keluar',
            fn () => $this->lifecycle->mutasiKeluar($siswa, $request->meta()),
            "Siswa {$siswa->nama} ditandai mutasi keluar.",
        );
    }

    public function luluskan(SiswaLifecycleRequest $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        return $this->runLifecycle(
            $request,
            $siswa,
            'lulus',
            fn () => $this->lifecycle->luluskan($siswa, $request->meta()),
            "Siswa {$siswa->nama} ditandai lulus.",
        );
    }

    public function setStatus(SiswaLifecycleRequest $request, Siswa $siswa): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $siswa->lembaga_id, (string) $user->lembaga_id), 404);

        $to = (string) $request->validated('status');

        return $this->runLifecycle(
            $request,
            $siswa,
            'set_status',
            fn () => $this->lifecycle->setStatus($siswa, $to, $request->meta()),
            "Status siswa {$siswa->nama} diperbarui.",
        );
    }

    /**
     * Aktivasi/nonaktif sementara hanya untuk siswa berstatus aktif (jeda operasional).
     * Perubahan status lifecycle ditangani aksi terpisah di halaman detail siswa.
     */
    private function rejectOperationalToggleUnlessAktif(Siswa $siswa): ?RedirectResponse
    {
        if ($siswa->status_siswa === SiswaStatus::AKTIF) {
            return null;
        }

        return redirect()
            ->route('admin.siswa.index')
            ->withErrors([
                'siswa' => 'Aktivasi/nonaktif sementara hanya untuk siswa berstatus aktif. Gunakan aksi lifecycle di halaman detail siswa untuk mengubah status.',
            ]);
    }

    /**
     * Menjalankan aksi lifecycle dengan penanganan error transisi yang seragam.
     *
     * @param  callable(): Siswa  $action
     */
    private function runLifecycle(
        Request $request,
        Siswa $siswa,
        string $event,
        callable $action,
        string $successMessage,
    ): RedirectResponse {
        $lembagaId = $siswa->lembaga_id;

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->record("siswa.lifecycle.{$event}", 'failed', [
                'reason' => $exception->getMessage(),
            ], subject: $siswa, lembagaId: $lembagaId, request: $request);

            return redirect()
                ->route('admin.siswa.show', $siswa)
                ->withErrors(['lifecycle' => $exception->getMessage()]);
        }

        $this->auditLogger->record("siswa.lifecycle.{$event}", 'success', [
            'status_siswa' => $siswa->refresh()->status_siswa,
        ], subject: $siswa, lembagaId: $lembagaId, request: $request);

        return redirect()
            ->route('admin.siswa.show', $siswa)
            ->with('status', $successMessage);
    }
}
