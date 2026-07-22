@extends('layouts.admin')

@section('title', $siswa->nama)

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.siswa.index') }}">Siswa</a> / {{ $siswa->nama }}
@endsection

@php
    use App\Support\Master\SiswaStatus;

    $jenisLabels = [
        'awal' => 'Penempatan awal',
        'kenaikan' => 'Kenaikan kelas',
        'pindah_kelas' => 'Pindah kelas',
        'mutasi_masuk' => 'Mutasi masuk',
        'mutasi_keluar' => 'Mutasi keluar',
        'lulus' => 'Lulus',
    ];

    $statusTargetLabels = [
        SiswaStatus::CALON => 'Calon',
        SiswaStatus::MUTASI_MASUK => 'Mutasi masuk',
    ];
@endphp

@section('content')
    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="callout-warning">
            <p><strong>Aksi tidak dapat diproses:</strong></p>
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <div class="card">
        <div class="card__header">
            <div>
                <h1 class="card__title font-display">{{ $siswa->nama }}</h1>
                <p class="card__meta">
                    NIS <strong>{{ $siswa->nis ?? '—' }}</strong>
                    @if ($siswa->nisn)
                        &middot; NISN <strong>{{ $siswa->nisn }}</strong>
                    @endif
                    &middot;
                    <x-ui.badge :tone="SiswaStatus::tone($siswa->status_siswa)">
                        {{ SiswaStatus::label($siswa->status_siswa) }}
                    </x-ui.badge>
                    @unless ($siswa->kelas)
                        <x-ui.badge tone="neutral">Belum ada kelas</x-ui.badge>
                    @endunless
                </p>
            </div>
            <div class="card__actions">
                @if ($canTempatkan)
                    <button type="button" class="btn btn-primary" data-modal-open="lifecycle-tempatkan">Tempatkan ke kelas</button>
                @endif
                @if ($canPindah)
                    <button type="button" class="btn btn-primary" data-modal-open="lifecycle-pindah">Pindah kelas</button>
                @endif
                @if (! empty($statusTargets))
                    <button type="button" class="btn btn-secondary" data-modal-open="lifecycle-set-status">Ubah status</button>
                @endif
                @if ($canMutasiKeluar)
                    <button type="button" class="btn btn-secondary" data-modal-open="lifecycle-mutasi-keluar">Mutasi keluar</button>
                @endif
                @if ($canLulus)
                    <button type="button" class="btn btn-secondary" data-modal-open="lifecycle-lulus">Luluskan</button>
                @endif
                <x-ui.button href="{{ route('admin.siswa.edit', $siswa) }}" variant="secondary">Ubah</x-ui.button>
            </div>
        </div>

        <dl class="detail-grid">
            <div>
                <dt class="detail-grid__label">Kelas</dt>
                <dd class="detail-grid__value">
                    @if ($siswa->kelas)
                        {{ $siswa->kelas->nama }}
                    @else
                        Belum ada kelas
                    @endif
                </dd>
            </div>
            <div>
                <dt class="detail-grid__label">Tahun ajaran</dt>
                <dd class="detail-grid__value">{{ $siswa->tahunAjaran->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Status berlaku sejak</dt>
                <dd class="detail-grid__value">{{ $siswa->status_at?->format('d/m/Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Alasan status</dt>
                <dd class="detail-grid__value">{{ $siswa->status_alasan ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Asal</dt>
                <dd class="detail-grid__value">{{ $siswa->status_asal ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Tujuan</dt>
                <dd class="detail-grid__value">{{ $siswa->status_tujuan ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Jenis kelamin</dt>
                <dd class="detail-grid__value">{{ $siswa->jenis_kelamin ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Tempat, tanggal lahir</dt>
                <dd class="detail-grid__value">
                    {{ $siswa->tempat_lahir ?? '—' }}@if ($siswa->tanggal_lahir), {{ $siswa->tanggal_lahir->format('d/m/Y') }}@endif
                </dd>
            </div>
            <div>
                <dt class="detail-grid__label">Email</dt>
                <dd class="detail-grid__value">{{ $siswa->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Telepon</dt>
                <dd class="detail-grid__value">{{ $siswa->telepon ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Alamat</dt>
                <dd class="detail-grid__value">{{ $siswa->alamat ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Nama wali</dt>
                <dd class="detail-grid__value">{{ $siswa->nama_wali ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Telepon wali</dt>
                <dd class="detail-grid__value">{{ $siswa->telepon_wali ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="page-header" style="margin-top: 2rem;">
        <div>
            <h2 class="page-header__title font-display">Riwayat penempatan</h2>
            <p class="page-header__description">
                Jejak enrollment siswa di kelas dan tahun ajaran.
            </p>
        </div>
    </div>

    @if ($penempatans->isEmpty())
        <x-ui.empty-state
            title="Belum ada riwayat penempatan"
            description="Penempatan akan muncul setelah siswa ditempatkan ke kelas."
        />
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>Jenis</th>
                    <th>Kelas</th>
                    <th>Tahun ajaran</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                </tr>
            </x-slot:thead>
            @foreach ($penempatans as $penempatan)
                <tr>
                    <td>{{ $jenisLabels[$penempatan->jenis] ?? $penempatan->jenis }}</td>
                    <td>{{ $penempatan->kelas->nama ?? '—' }}</td>
                    <td>{{ $penempatan->tahunAjaran->nama ?? '—' }}</td>
                    <td>{{ $penempatan->mulai_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        @if ($penempatan->selesai_at)
                            {{ $penempatan->selesai_at->format('d/m/Y') }}
                        @else
                            <x-ui.badge tone="ok">Berjalan</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($canTempatkan)
        <x-ui.modal id="lifecycle-tempatkan" title="Tempatkan ke kelas">
            <p>Menempatkan <strong>{{ $siswa->nama }}</strong> ke kelas akan mengubah status menjadi <strong>Aktif</strong>.</p>
            <form id="lifecycle-tempatkan-form" method="POST" action="{{ route('admin.siswa.lifecycle.tempatkan', $siswa) }}">
                @csrf
                <x-ui.select name="kelas_id" label="Kelas" required>
                    <option value="">— Pilih kelas —</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}">{{ $kelas->nama }} ({{ $kelas->tahunAjaran?->nama ?? '—' }})</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input name="mulai_at" type="date" label="Tanggal mulai" hint="Kosongkan untuk memakai tanggal hari ini." />
            </form>
            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <x-ui.button type="submit" form="lifecycle-tempatkan-form">Tempatkan</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif

    @if ($canPindah)
        <x-ui.modal id="lifecycle-pindah" title="Pindah kelas">
            <p>Memindahkan <strong>{{ $siswa->nama }}</strong> akan menutup penempatan berjalan dan membuka penempatan baru.</p>
            <form id="lifecycle-pindah-form" method="POST" action="{{ route('admin.siswa.lifecycle.pindah', $siswa) }}">
                @csrf
                <x-ui.select name="kelas_id" label="Kelas tujuan" required>
                    <option value="">— Pilih kelas —</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}">{{ $kelas->nama }} ({{ $kelas->tahunAjaran?->nama ?? '—' }})</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input name="mulai_at" type="date" label="Tanggal mulai" hint="Kosongkan untuk memakai tanggal hari ini." />
            </form>
            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <x-ui.button type="submit" form="lifecycle-pindah-form">Pindahkan</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif

    @if (! empty($statusTargets))
        <x-ui.modal id="lifecycle-set-status" title="Ubah status">
            <form id="lifecycle-set-status-form" method="POST" action="{{ route('admin.siswa.lifecycle.set_status', $siswa) }}">
                @csrf
                <x-ui.select name="status" label="Status baru" required>
                    <option value="">— Pilih status —</option>
                    @foreach ($statusTargets as $target)
                        <option value="{{ $target }}">{{ $statusTargetLabels[$target] ?? $target }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input name="alasan" label="Alasan" />
                <x-ui.input name="asal" label="Asal (opsional)" />
                <x-ui.input name="status_at" type="date" label="Berlaku sejak" hint="Kosongkan untuk memakai tanggal hari ini." />
            </form>
            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <x-ui.button type="submit" form="lifecycle-set-status-form">Simpan</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif

    @if ($canMutasiKeluar)
        <x-ui.modal id="lifecycle-mutasi-keluar" title="Mutasi keluar">
            <p>Menandai <strong>{{ $siswa->nama }}</strong> mutasi keluar akan mengosongkan kelas dan menutup penempatan berjalan.</p>
            <form id="lifecycle-mutasi-keluar-form" method="POST" action="{{ route('admin.siswa.lifecycle.mutasi_keluar', $siswa) }}">
                @csrf
                <x-ui.input name="alasan" label="Alasan" />
                <x-ui.input name="tujuan" label="Sekolah/lembaga tujuan (opsional)" />
                <x-ui.input name="status_at" type="date" label="Tanggal efektif" hint="Kosongkan untuk memakai tanggal hari ini." />
            </form>
            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <x-ui.button variant="danger" type="submit" form="lifecycle-mutasi-keluar-form">Mutasi keluar</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif

    @if ($canLulus)
        <x-ui.modal id="lifecycle-lulus" title="Luluskan siswa">
            <p>Menandai <strong>{{ $siswa->nama }}</strong> lulus akan mengosongkan kelas dan menutup penempatan berjalan.</p>
            <form id="lifecycle-lulus-form" method="POST" action="{{ route('admin.siswa.lifecycle.lulus', $siswa) }}">
                @csrf
                <x-ui.input name="alasan" label="Catatan (opsional)" />
                <x-ui.input name="status_at" type="date" label="Tanggal efektif" hint="Kosongkan untuk memakai tanggal hari ini." />
            </form>
            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <x-ui.button type="submit" form="lifecycle-lulus-form">Luluskan</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif
@endsection
