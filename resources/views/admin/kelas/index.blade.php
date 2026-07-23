@extends('layouts.admin')

@section('title', 'Kelas')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Kelas
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Kelas</h1>
            <p class="page-header__description">
                Kelola data kelas lembaga Anda per tahun ajaran.
                Import siswa dilakukan dari halaman Detail kelas (klik nama kelas di tabel), bukan dari tombol Import kelas di atas.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.kelas.template') }}" variant="secondary">Unduh template kelas</x-ui.button>
            <button type="button" class="btn btn-secondary" data-modal-open="import-kelas">Import kelas</button>
            <x-ui.button href="{{ route('admin.kelas.create') }}">Tambah kelas</x-ui.button>
        </div>
    </div>

    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    @if (session('import_errors'))
        <div class="callout-warning">
            <p><strong>Detail baris gagal:</strong></p>
            <ul>
                @foreach (session('import_errors') as $error)
                    <li>Baris {{ $error['row'] }}: {{ $error['message'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($errors->any())
        <div class="callout-warning">
            <p><strong>Periksa kembali data yang dikirim:</strong></p>
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.kelas.index') }}" class="toolbar" role="search">
        <select name="tahun_ajaran_id" class="field-control" aria-label="Filter tahun ajaran">
            <option value="" @selected($tahunAjaranId === null || $tahunAjaranId === '')>Semua tahun ajaran</option>
            @foreach ($tahunAjarans as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId === $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}
                </option>
            @endforeach
        </select>
        <input
            type="search"
            name="q"
            value="{{ $q }}"
            placeholder="Cari nama kelas"
            class="field-control"
            aria-label="Cari kelas"
        >
        <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
        @if ($q !== '' || ($tahunAjaranId !== null && $tahunAjaranId !== ''))
            <x-ui.button href="{{ route('admin.kelas.index') }}" variant="ghost">Reset</x-ui.button>
        @endif
    </form>

    @if ($kelasList->isEmpty())
        @php
            $emptyDescription = ($q !== '' || ($tahunAjaranId !== null && $tahunAjaranId !== ''))
                ? 'Tidak ada kelas yang cocok dengan filter.'
                : 'Mulai dengan menambahkan kelas pertama.';
        @endphp
        <x-ui.empty-state title="Belum ada kelas" :description="$emptyDescription">
            <x-ui.button href="{{ route('admin.kelas.create') }}">Tambah kelas</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>Nama</th>
                    <th>Tahun ajaran</th>
                    <th>Tingkat</th>
                    <th>Wali kelas</th>
                    <th>Jumlah siswa</th>
                    <th>Aksi</th>
                </tr>
            </x-slot:thead>
            @foreach ($kelasList as $item)
                <tr>
                    <td>
                        <a href="{{ route('admin.kelas.show', $item) }}">{{ $item->nama }}</a>
                    </td>
                    <td>{{ $item->tahunAjaran?->nama ?? '—' }}</td>
                    <td>{{ $item->tingkat ?? '—' }}</td>
                    <td>{{ $item->waliKelas?->nama ?? '—' }}</td>
                    <td>{{ $item->siswa_count }}</td>
                    <td>
                        <div class="table-actions">
                            <x-ui.button
                                href="{{ route('admin.kelas.show', $item) }}"
                                variant="secondary"
                                class="btn-sm"
                            >
                                Detail
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('admin.kelas.edit', $item) }}"
                                variant="secondary"
                                class="btn-sm"
                            >
                                Ubah
                            </x-ui.button>
                            <button type="button" class="btn btn-danger btn-sm" data-modal-open="delete-kelas-{{ $item->id }}">
                                Hapus
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$kelasList" />
    @endif

    @foreach ($kelasList as $item)
        <x-ui.modal id="delete-kelas-{{ $item->id }}" title="Hapus kelas?">
            <p>
                Menghapus <strong>{{ $item->nama }}</strong> akan berdampak:
            </p>
            <ul>
                <li>Penghapusan bersifat permanen; nama kelas bisa dipakai lagi.</li>
                <li>Jika masih memiliki siswa, penghapusan akan ditolak.</li>
            </ul>

            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.kelas.destroy', $item) }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" type="submit">Hapus kelas</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.modal>
    @endforeach

    <x-ui.modal id="import-kelas" title="Import data kelas">
        <p>Unggah file Excel (.xlsx) <strong>template kelas</strong> (kolom: nama, tahun_ajaran, …).</p>
        <p>Untuk import <strong>siswa</strong>, buka Detail kelas → Unduh template siswa → Import siswa ke kelas ini.</p>

        <form id="import-kelas-form" method="POST" action="{{ route('admin.kelas.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="import-kelas-file" class="field-label">File Excel</label>
                <input id="import-kelas-file" type="file" name="file" accept=".xlsx,.xls" class="field-control" required>
            </div>
        </form>

        <x-slot:actions>
            <form method="dialog">
                <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
            </form>
            <x-ui.button type="submit" form="import-kelas-form">Import</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
@endsection
