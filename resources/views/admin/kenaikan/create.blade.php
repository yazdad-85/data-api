@extends('layouts.admin')

@section('title', 'Kenaikan kelas — '.$kelas->nama)

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.kelas.index') }}">Kelas</a> /
    <a href="{{ route('admin.kelas.show', $kelas) }}">{{ $kelas->nama }}</a> / Kenaikan kelas
@endsection

@php
    use App\Support\Master\SiswaStatus;

    $aksiOptions = [
        'naik' => 'Naik / pindah ke kelas tujuan',
        'tinggal' => 'Tinggal (tetap di kelas asal)',
        'lulus' => 'Luluskan',
        'mutasi_keluar' => 'Mutasi keluar',
    ];
@endphp

@section('content')
    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="callout-warning">
            <p><strong>Batch tidak dapat diproses:</strong></p>
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    @if (session('kenaikan_errors'))
        <div class="callout-warning">
            <p><strong>Detail baris gagal:</strong></p>
            <ul>
                @foreach (session('kenaikan_errors') as $error)
                    <li>
                        @if ($error['row'] > 0)Baris {{ $error['row'] }}: @endif{{ $error['message'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Kenaikan kelas</h1>
            <p class="page-header__description">
                Proses siswa aktif di kelas <strong>{{ $kelas->nama }}</strong>
                ({{ $kelas->tahunAjaran?->nama ?? '—' }}). Semua perubahan diproses dalam satu transaksi:
                jika ada satu baris gagal, seluruh batch dibatalkan.
            </p>
        </div>
    </div>

    <div class="callout-info">
        <p><strong>Urutan kerja yang benar:</strong></p>
        <ol style="margin: 0.35rem 0 0; padding-left: 1.2rem;">
            <li>Pastikan <strong>Tahun Ajaran</strong> tujuan sudah dibuat —
                <a href="{{ route('admin.tahun-ajaran.create') }}">Tambah tahun ajaran</a>.</li>
            <li>Pastikan <strong>kelas tujuan</strong> untuk tahun ajaran itu sudah dibuat (mis. kelas 8A) —
                <a href="{{ route('admin.kelas.create') }}">Tambah kelas</a>.</li>
            <li>Baru pilih "Kelas tujuan default" di bawah, lalu proses.</li>
        </ol>
        <p style="margin: 0.5rem 0 0;">
            Perlu memproses <strong>banyak kelas sekaligus</strong> (mis. semua kelas 7 naik ke kelas 8)?
            Gunakan <a href="{{ route('admin.kenaikan-massal.create') }}">Kenaikan kelas massal</a> supaya tidak
            perlu buka halaman ini satu per satu.
        </p>
    </div>

    @if ($siswa->isEmpty())
        <x-ui.empty-state
            title="Tidak ada siswa aktif"
            description="Kenaikan kelas hanya memproses siswa berstatus aktif di kelas ini."
        >
            <x-ui.button href="{{ route('admin.kelas.show', $kelas) }}" variant="secondary">Kembali ke kelas</x-ui.button>
        </x-ui.empty-state>
    @else
        <form method="POST" action="{{ route('admin.kelas.kenaikan.store', $kelas) }}">
            @csrf

            <div class="card">
                <div class="toolbar">
                    <x-ui.select name="tahun_tujuan_id" label="Tahun ajaran tujuan (opsional)">
                        <option value="">— Pilih tahun ajaran —</option>
                        @foreach ($tahunAjarans as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected(old('tahun_tujuan_id') === $tahunAjaran->id)>
                                {{ $tahunAjaran->nama }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select
                        name="kelas_tujuan_default_id"
                        label="Kelas tujuan default (untuk aksi naik)"
                        :hint="$kelasTujuan->isEmpty() ? 'Belum ada kelas lain untuk dipilih — buat kelas tujuan dulu.' : null"
                    >
                        <option value="">— Pilih kelas tujuan —</option>
                        @foreach ($kelasTujuan as $target)
                            <option value="{{ $target->id }}" @selected(old('kelas_tujuan_default_id') === $target->id)>
                                {{ $target->nama }} ({{ $target->tahunAjaran?->nama ?? '—' }})
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input name="efektif_at" type="date" label="Tanggal efektif" hint="Kosongkan untuk memakai tanggal hari ini." :value="old('efektif_at')" />
                </div>
            </div>

            <x-ui.table>
                <x-slot:thead>
                    <tr>
                        <th>Nama</th>
                        <th>NIS</th>
                        <th>Status</th>
                        <th>Aksi</th>
                        <th>Kelas tujuan (override)</th>
                    </tr>
                </x-slot:thead>
                @foreach ($siswa as $index => $item)
                    <tr>
                        <td>
                            {{ $item->nama }}
                            <input type="hidden" name="rows[{{ $index }}][siswa_id]" value="{{ $item->id }}">
                        </td>
                        <td>{{ $item->nis ?? '—' }}</td>
                        <td>
                            <x-ui.badge :tone="SiswaStatus::tone($item->status_siswa)">
                                {{ SiswaStatus::label($item->status_siswa) }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <x-ui.select name="rows[{{ $index }}][aksi]" :id="'aksi-'.$index" aria-label="Aksi untuk {{ $item->nama }}">
                                @foreach ($aksiOptions as $value => $labelText)
                                    <option value="{{ $value }}" @selected(old('rows.'.$index.'.aksi', 'naik') === $value)>
                                        {{ $labelText }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </td>
                        <td>
                            <x-ui.select name="rows[{{ $index }}][kelas_tujuan_id]" :id="'kelas-'.$index" aria-label="Kelas tujuan untuk {{ $item->nama }}">
                                <option value="">— Gunakan default —</option>
                                @foreach ($kelasTujuan as $target)
                                    <option value="{{ $target->id }}" @selected(old('rows.'.$index.'.kelas_tujuan_id') === $target->id)>
                                        {{ $target->nama }} ({{ $target->tahunAjaran?->nama ?? '—' }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="page-header__actions" style="margin-top: 1.5rem;">
                <x-ui.button href="{{ route('admin.kelas.show', $kelas) }}" variant="secondary">Batal</x-ui.button>
                <x-ui.button type="submit">Proses kenaikan</x-ui.button>
            </div>
        </form>
    @endif
@endsection
