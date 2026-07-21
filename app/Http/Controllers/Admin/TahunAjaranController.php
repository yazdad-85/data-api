<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EnsuresAdminLembaga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTahunAjaranRequest;
use App\Http\Requests\Admin\UpdateTahunAjaranRequest;
use App\Models\TahunAjaran;
use App\Services\AuditLogger;
use App\Support\Master\TahunAjaranNamer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TahunAjaranController extends Controller
{
    use EnsuresAdminLembaga;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->adminLembaga();

        $tahunAjarans = TahunAjaran::query()
            ->orderByDesc('nama')
            ->paginate(15)
            ->withQueryString();

        return view('admin.tahun-ajaran.index', compact('tahunAjarans'));
    }

    public function create(): View
    {
        $this->adminLembaga();

        return view('admin.tahun-ajaran.create');
    }

    public function store(StoreTahunAjaranRequest $request): RedirectResponse
    {
        $user = $this->adminLembaga();

        $tahunMulai = (int) $request->validated('tahun_mulai');
        $nama = TahunAjaranNamer::fromTahunMulai($tahunMulai);

        $trashed = TahunAjaran::onlyTrashed()
            ->where('lembaga_id', $user->lembaga_id)
            ->where('nama', $nama)
            ->first();

        if ($trashed !== null) {
            $trashed->restore();
            $trashed->update([
                'tanggal_mulai' => $request->validated('tanggal_mulai'),
                'tanggal_selesai' => $request->validated('tanggal_selesai'),
                'is_aktif' => false,
            ]);
            $tahunAjaran = $trashed->fresh();
            $auditAction = 'tahun_ajaran.restore';
            $status = "Tahun ajaran {$tahunAjaran->nama} dipulihkan.";
        } else {
            $tahunAjaran = TahunAjaran::query()->create([
                'lembaga_id' => $user->lembaga_id,
                'nama' => $nama,
                'tanggal_mulai' => $request->validated('tanggal_mulai'),
                'tanggal_selesai' => $request->validated('tanggal_selesai'),
                'is_aktif' => false,
            ]);
            $auditAction = 'tahun_ajaran.create';
            $status = "Tahun ajaran {$tahunAjaran->nama} berhasil dibuat.";
        }

        $this->auditLogger->record($auditAction, 'success', [
            'nama' => $tahunAjaran->nama,
        ], subject: $tahunAjaran, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.tahun-ajaran.index')
            ->with('status', $status);
    }

    public function edit(TahunAjaran $tahunAjaran): View
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $tahunAjaran->lembaga_id, (string) $user->lembaga_id), 404);

        return view('admin.tahun-ajaran.edit', compact('tahunAjaran'));
    }

    public function update(UpdateTahunAjaranRequest $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $tahunAjaran->lembaga_id, (string) $user->lembaga_id), 404);

        $tahunAjaran->update($request->validated());

        $this->auditLogger->record('tahun_ajaran.update', 'success', [
            'nama' => $tahunAjaran->nama,
        ], subject: $tahunAjaran, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.tahun-ajaran.index')
            ->with('status', "Tahun ajaran {$tahunAjaran->nama} berhasil diperbarui.");
    }

    public function activate(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $tahunAjaran->lembaga_id, (string) $user->lembaga_id), 404);

        DB::transaction(function () use ($user, $tahunAjaran) {
            TahunAjaran::query()
                ->where('lembaga_id', $user->lembaga_id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);

            $tahunAjaran->update(['is_aktif' => true]);
        });

        $this->auditLogger->record('tahun_ajaran.activate', 'success', [
            'nama' => $tahunAjaran->nama,
        ], subject: $tahunAjaran, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.tahun-ajaran.index')
            ->with('status', "Tahun ajaran {$tahunAjaran->nama} diaktifkan.");
    }

    public function destroy(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        $user = $this->adminLembaga();
        abort_unless(hash_equals((string) $tahunAjaran->lembaga_id, (string) $user->lembaga_id), 404);

        if ($tahunAjaran->kelas()->exists() || $tahunAjaran->siswa()->exists()) {
            return redirect()
                ->route('admin.tahun-ajaran.index')
                ->withErrors([
                    'tahun_ajaran' => "Tahun ajaran {$tahunAjaran->nama} tidak dapat dihapus karena masih dipakai kelas atau siswa.",
                ]);
        }

        $nama = $tahunAjaran->nama;
        $tahunAjaran->forceDelete();

        $this->auditLogger->record('tahun_ajaran.delete', 'success', [
            'nama' => $nama,
        ], subject: $tahunAjaran, lembagaId: $user->lembaga_id, request: $request);

        return redirect()
            ->route('admin.tahun-ajaran.index')
            ->with('status', "Tahun ajaran {$nama} dihapus permanen.");
    }
}
