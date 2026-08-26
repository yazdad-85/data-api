@extends('layouts.admin')

@section('title', 'Kenaikan kelas massal')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.kelas.index') }}">Kelas</a> / Kenaikan kelas massal
@endsection

@php
    $totalSiswaAktif = $kelasAsalList->sum('siswa_aktif_count');
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

    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Kenaikan kelas massal</h1>
            <p class="page-header__description">
                Petakan banyak kelas asal ke kelas tujuan sekaligus. Semua siswa aktif di tiap kelas asal
                akan diproses sebagai "naik" ke kelas tujuan yang dipetakan. Tiap kelas diproses terpisah,
                jadi kegagalan satu kelas tidak membatalkan kelas lain.
            </p>
        </div>
    </div>

    <div class="callout-info">
        <p><strong>Urutan kerja yang benar:</strong></p>
        <ol style="margin: 0.35rem 0 0; padding-left: 1.2rem;">
            <li>Pastikan <strong>Tahun Ajaran</strong> tujuan sudah dibuat —
                <a href="{{ route('admin.tahun-ajaran.create') }}">Tambah tahun ajaran</a>.</li>
            <li>Pastikan <strong>kelas-kelas</strong> untuk tahun ajaran tujuan sudah dibuat (mis. kelas 8A, 8B) —
                <a href="{{ route('admin.kelas.create') }}">Tambah kelas</a>.</li>
            <li>Pilih tahun ajaran asal &amp; tujuan di bawah, lalu petakan tiap kelas asal ke kelas tujuannya.</li>
        </ol>
        <p style="margin: 0.5rem 0 0;">
            Siswa yang <strong>tinggal kelas, lulus, atau mutasi keluar</strong> tidak diproses di sini — gunakan
            halaman "Kenaikan kelas" per kelas (buka dari <a href="{{ route('admin.kelas.index') }}">Detail kelas</a>)
            untuk siswa tersebut, sebelum atau sesudah proses massal ini.
        </p>
    </div>

    <form method="GET" action="{{ route('admin.kenaikan-massal.create') }}" class="toolbar" role="search">
        <x-ui.select name="tahun_asal_id" label="Tahun ajaran asal">
            <option value="" @selected($tahunAsalId === '')>— Pilih —</option>
            @foreach ($tahunAjarans as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunAsalId === $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}
                </option>
            @endforeach
        </x-ui.select>

        <x-ui.select name="tahun_tujuan_id" label="Tahun ajaran tujuan">
            <option value="" @selected($tahunTujuanId === '')>— Pilih —</option>
            @foreach ($tahunAjarans as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunTujuanId === $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}
                </option>
            @endforeach
        </x-ui.select>

        <x-ui.button type="submit" variant="secondary">Tampilkan</x-ui.button>
    </form>

    @if ($tahunAsalId === '' || $tahunTujuanId === '')
        <x-ui.empty-state
            title="Pilih tahun ajaran asal dan tujuan"
            description="Pilih keduanya lalu klik Tampilkan untuk melihat daftar kelas yang bisa dipetakan."
        />
    @elseif ($tahunTujuanBelumPunyaKelas)
        <x-ui.empty-state
            title="Kelas untuk tahun ajaran tujuan belum ada"
            description="Buat kelas-kelas untuk tahun ajaran tujuan terlebih dahulu (mis. kelas 8A, 8B), baru kembali ke sini."
        >
            <x-ui.button href="{{ route('admin.kelas.create') }}">Tambah kelas</x-ui.button>
        </x-ui.empty-state>
    @elseif ($kelasAsalList->isEmpty())
        <x-ui.empty-state
            title="Tidak ada kelas di tahun ajaran asal"
            description="Tidak ditemukan kelas pada tahun ajaran asal yang dipilih."
        />
    @else
        @if (session('kenaikan_massal_hasil'))
            <div class="card">
                <h2 class="font-display" style="margin-top: 0;">Hasil proses terakhir</h2>
                <x-ui.table>
                    <x-slot:thead>
                        <tr>
                            <th>Kelas asal</th>
                            <th>Kelas tujuan</th>
                            <th>Berhasil</th>
                            <th>Gagal</th>
                        </tr>
                    </x-slot:thead>
                    @foreach (session('kenaikan_massal_hasil') as $item)
                        <tr>
                            <td>{{ $item['kelas_asal_nama'] }}</td>
                            <td>{{ $item['kelas_tujuan_nama'] }}</td>
                            <td>{{ $item['success'] }}</td>
                            <td>
                                {{ $item['failed'] }}
                                @if ($item['failed'] > 0)
                                    <div class="table-subtext">
                                        <a href="{{ route('admin.kelas.kenaikan.create', $item['kelas_asal_id']) }}">Buka halaman per-kelas</a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.kenaikan-massal.store') }}" id="kenaikan-massal-form">
            @csrf
            <input type="hidden" name="tahun_asal_id" value="{{ $tahunAsalId }}">
            <input type="hidden" name="tahun_tujuan_id" value="{{ $tahunTujuanId }}">

            <div class="card">
                <div class="toolbar">
                    <x-ui.input name="efektif_at" type="date" label="Tanggal efektif" hint="Kosongkan untuk memakai tanggal hari ini." :value="old('efektif_at')" />
                </div>
            </div>

            <x-ui.table>
                <x-slot:thead>
                    <tr>
                        <th>Kelas asal</th>
                        <th>Siswa aktif</th>
                        <th>Kelas tujuan</th>
                    </tr>
                </x-slot:thead>
                @foreach ($kelasAsalList as $index => $kelas)
                    <tr>
                        <td>
                            {{ $kelas->nama }}
                            <input type="hidden" name="mappings[{{ $index }}][kelas_asal_id]" value="{{ $kelas->id }}">
                        </td>
                        <td>{{ $kelas->siswa_aktif_count }}</td>
                        <td>
                            <x-ui.select
                                name="mappings[{{ $index }}][kelas_tujuan_id]"
                                :id="'kelas-tujuan-'.$index"
                                aria-label="Kelas tujuan untuk {{ $kelas->nama }}"
                                required
                                :error="$errors->first('mappings.'.$index.'.kelas_tujuan_id')"
                            >
                                <option value="">— Pilih kelas tujuan —</option>
                                @foreach ($kelasTujuanList as $target)
                                    <option value="{{ $target->id }}" @selected(old('mappings.'.$index.'.kelas_tujuan_id') === $target->id)>
                                        {{ $target->nama }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="page-header__actions" style="margin-top: 1.5rem;">
                <x-ui.button href="{{ route('admin.kelas.index') }}" variant="secondary">Batal</x-ui.button>
                <button type="button" class="btn btn-primary" data-modal-open="confirm-kenaikan-massal">Proses kenaikan massal</button>
            </div>
        </form>

        <x-ui.modal id="confirm-kenaikan-massal" title="Proses kenaikan kelas massal?">
            <p>
                <strong>{{ $kelasAsalList->count() }}</strong> kelas akan diproses, total
                <strong>{{ $totalSiswaAktif }}</strong> siswa aktif akan naik ke kelas tujuan yang dipetakan.
            </p>
            <p>Pastikan pemetaan kelas tujuan di setiap baris sudah benar sebelum melanjutkan.</p>

            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Periksa lagi</x-ui.button>
                </form>
                <x-ui.button type="submit" form="kenaikan-massal-form">Ya, proses sekarang</x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif
@endsection
