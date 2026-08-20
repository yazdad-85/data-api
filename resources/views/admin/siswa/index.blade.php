@extends('layouts.admin')

@section('title', 'Siswa')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Siswa
@endsection

@php
    use App\Support\Master\SiswaStatus;
@endphp

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Siswa</h1>
            <p class="page-header__description">
                Kelola data siswa lembaga Anda.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.siswa.create') }}">Tambah siswa</x-ui.button>
            <x-ui.button href="{{ route('admin.siswa.create', ['jenis_masuk' => 'mutasi_masuk']) }}" variant="secondary">Mutasi masuk</x-ui.button>
        </div>
    </div>

    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="callout-warning">
            <p><strong>Periksa kembali data yang dikirim:</strong></p>
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.siswa.index') }}" class="toolbar" role="search">
        <select name="tahun_ajaran_id" class="field-control" aria-label="Filter tahun ajaran">
            <option value="" @selected($tahunAjaranId === null || $tahunAjaranId === '')>Semua tahun ajaran</option>
            @foreach ($tahunAjarans as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId === $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}
                </option>
            @endforeach
        </select>
        <select name="kelas_id" class="field-control" aria-label="Filter kelas">
            <option value="" @selected($kelasId === null || $kelasId === '')>Semua kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected($kelasId === $kelas->id)>
                    {{ $kelas->nama }}@if ($kelas->tahunAjaran) ({{ $kelas->tahunAjaran->nama }})@endif
                </option>
            @endforeach
        </select>
        <select name="status_siswa" class="field-control" aria-label="Filter status siswa">
            <option value="" @selected($statusSiswa === '')>Semua status</option>
            @foreach (SiswaStatus::ALL as $status)
                <option value="{{ $status }}" @selected($statusSiswa === $status)>
                    {{ SiswaStatus::label($status) }}
                </option>
            @endforeach
        </select>
        <input
            type="search"
            name="q"
            value="{{ $q }}"
            placeholder="Cari nama, NIS, NISN, ayah, atau ibu"
            class="field-control"
            aria-label="Cari siswa"
        >
        <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
        @if ($q !== '' || ($kelasId !== null && $kelasId !== '') || ($tahunAjaranId !== null && $tahunAjaranId !== '') || $statusSiswa !== '')
            <x-ui.button href="{{ route('admin.siswa.index') }}" variant="ghost">Reset</x-ui.button>
        @endif
    </form>

    @if ($siswas->isEmpty())
        @php
            $emptyDescription = ($q !== '' || ($kelasId !== null && $kelasId !== '') || ($tahunAjaranId !== null && $tahunAjaranId !== '') || $statusSiswa !== '')
                ? 'Tidak ada siswa yang cocok dengan filter.'
                : 'Mulai dengan menambahkan siswa pertama.';
        @endphp
        <x-ui.empty-state title="Belum ada siswa" :description="$emptyDescription">
            <x-ui.button href="{{ route('admin.siswa.create') }}">Tambah siswa</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>Nama</th>
                    <th>NIS</th>
                    <th>NISN</th>
                    <th>Kelas</th>
                    <th>Status keluarga</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </x-slot:thead>
            @foreach ($siswas as $siswa)
                <tr>
                    <td>{{ $siswa->nama }}</td>
                    <td>{{ $siswa->nis ?? '—' }}</td>
                    <td>{{ $siswa->nisn ?? '—' }}</td>
                    <td>
                        @if ($siswa->kelas)
                            <x-ui.badge tone="neutral">{{ $siswa->kelas->nama }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral">Belum ada kelas</x-ui.badge>
                        @endif
                    </td>
                    <td>{{ $siswa->status_keluarga ?? '—' }}</td>
                    <td>
                        <x-ui.badge :tone="SiswaStatus::tone($siswa->status_siswa)">
                            {{ SiswaStatus::label($siswa->status_siswa) }}
                        </x-ui.badge>
                    </td>
                    <td>
                        <div class="table-actions">
                            <x-ui.button href="{{ route('admin.siswa.show', $siswa) }}" class="btn-sm">
                                Detail
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('admin.siswa.edit', $siswa) }}"
                                variant="secondary"
                                class="btn-sm"
                            >
                                Ubah
                            </x-ui.button>
                            @if ($siswa->is_active)
                                <form method="POST" action="{{ route('admin.siswa.deactivate', $siswa) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Nonaktifkan</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.siswa.activate', $siswa) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Aktifkan</button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-danger btn-sm" data-modal-open="delete-siswa-{{ $siswa->id }}">
                                Hapus
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$siswas" />
    @endif

    @foreach ($siswas as $siswa)
        <x-ui.modal id="delete-siswa-{{ $siswa->id }}" title="Hapus siswa?">
            <p>
                Menghapus <strong>{{ $siswa->nama }}</strong> akan berdampak:
            </p>
            <ul>
                <li>Siswa tidak lagi muncul pada daftar dan pencarian.</li>
                <li>Data tetap tersimpan (soft delete) dan NIS/NISN tetap terdaftar.</li>
            </ul>

            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.siswa.destroy', $siswa) }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" type="submit">Hapus siswa</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.modal>
    @endforeach
@endsection
