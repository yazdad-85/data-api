@extends('layouts.admin')

@section('title', 'Distribusi SPMB')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.siswa.index') }}">Siswa</a> / Distribusi SPMB
@endsection

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

    @if (session('spmb_errors'))
        <div class="callout-warning">
            <p><strong>Detail kegagalan:</strong></p>
            <ul>
                @foreach (session('spmb_errors') as $error)
                    <li>
                        @if ($error['row'] > 0)Baris {{ $error['row'] }}: @endif{{ $error['message'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Distribusi SPMB</h1>
            <p class="page-header__description">
                Tempatkan calon murid (SPMB) ke satu kelas tujuan sekaligus. Centang calon murid yang
                dituju, pilih satu kelas tujuan, lalu proses. Seluruh batch diproses dalam satu transaksi:
                jika ada satu siswa gagal, tidak ada perubahan yang disimpan.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.spmb-calon.create') }}" variant="secondary">Import calon murid (Excel)</x-ui.button>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.spmb-distribusi.create') }}" class="toolbar" role="search">
        <x-ui.select name="tahun_ajaran_id" label="Tahun ajaran">
            <option value="" @selected($tahunAjaranId === '')>Semua tahun ajaran</option>
            @foreach ($tahunAjarans as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId === $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}
                </option>
            @endforeach
        </x-ui.select>

        <x-ui.select name="tingkat" label="Tingkat kelas tujuan">
            <option value="" @selected($tingkat === '')>Semua tingkat</option>
            @foreach ($tingkatOptions as $option)
                <option value="{{ $option }}" @selected($tingkat === $option)>{{ $option }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
    </form>

    @if ($calonSiswa->isEmpty())
        <x-ui.empty-state
            title="Belum ada calon murid"
            description="Tidak ada calon murid (status CALON) yang cocok dengan filter saat ini."
        >
            <x-ui.button href="{{ route('admin.siswa.create') }}" variant="secondary">Tambah calon murid</x-ui.button>
            <x-ui.button href="{{ route('admin.spmb-calon.create') }}" variant="secondary">Import calon murid (Excel)</x-ui.button>
        </x-ui.empty-state>
    @else
        <form method="POST" action="{{ route('admin.spmb-distribusi.store') }}">
            @csrf

            <div class="card">
                <div class="toolbar">
                    <x-ui.select name="kelas_id" label="Kelas tujuan" required :error="$errors->first('kelas_id')">
                        <option value="">— Pilih kelas tujuan —</option>
                        @foreach ($kelasTujuanList as $kelas)
                            <option value="{{ $kelas->id }}" @selected(old('kelas_id') === $kelas->id)>
                                {{ $kelas->nama }} ({{ $kelas->tahunAjaran?->nama ?? '—' }})
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input
                        name="mulai_at"
                        type="date"
                        label="Tanggal mulai (opsional)"
                        hint="Kosongkan untuk memakai tanggal hari ini."
                        :value="old('mulai_at')"
                    />
                </div>
            </div>

            <x-ui.table>
                <x-slot:thead>
                    <tr>
                        <th>
                            <input type="checkbox" data-select-all aria-label="Pilih semua calon murid">
                        </th>
                        <th>Nama</th>
                        <th>NIS/NISN</th>
                        <th>Tahun ajaran</th>
                    </tr>
                </x-slot:thead>
                @foreach ($calonSiswa as $siswa)
                    <tr>
                        <td>
                            <input
                                type="checkbox"
                                name="siswa_ids[]"
                                value="{{ $siswa->id }}"
                                data-select-item
                                aria-label="Pilih {{ $siswa->nama }}"
                                @checked(in_array($siswa->id, old('siswa_ids', []), true))
                            >
                        </td>
                        <td>{{ $siswa->nama }}</td>
                        <td>
                            {{ $siswa->nis ?? '—' }}
                            @if ($siswa->nisn)
                                <span class="text-muted">/ {{ $siswa->nisn }}</span>
                            @endif
                        </td>
                        <td>{{ $siswa->tahunAjaran?->nama ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="page-header__actions" style="margin-top: 1.5rem;">
                <x-ui.button href="{{ route('admin.siswa.index') }}" variant="secondary">Batal</x-ui.button>
                <x-ui.button type="submit">Distribusikan ke kelas</x-ui.button>
            </div>
        </form>
    @endif
@endsection
