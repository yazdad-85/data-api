@extends('layouts.admin')

@section('title', $kelas->nama)

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.kelas.index') }}">Kelas</a> / {{ $kelas->nama }}
@endsection

@php
    use App\Support\Master\SiswaStatus;
@endphp

@section('content')
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

    <div class="card">
        <div class="card__header">
            <div>
                <h1 class="card__title font-display">{{ $kelas->nama }}</h1>
                <p class="card__meta">
                    Tahun ajaran <strong>{{ $kelas->tahunAjaran?->nama ?? '—' }}</strong>
                    @if ($kelas->tingkat)
                        &middot; Tingkat <strong>{{ $kelas->tingkat }}</strong>
                    @endif
                    @if ($kelas->waliKelas)
                        &middot; Wali kelas <strong>{{ $kelas->waliKelas->nama }}</strong>
                    @endif
                </p>
            </div>
            <div class="card__actions">
                <x-ui.button href="{{ route('admin.kelas.edit', $kelas) }}" variant="secondary">Ubah</x-ui.button>
                <button type="button" class="btn btn-danger" data-modal-open="delete-kelas-show">Hapus</button>
            </div>
        </div>
    </div>

    <div class="page-header" style="margin-top: 2rem;">
        <div>
            <h2 class="page-header__title font-display">Siswa di kelas ini</h2>
            <p class="page-header__description">
                Daftar siswa yang terdaftar di kelas {{ $kelas->nama }}.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.kelas.kenaikan.create', $kelas) }}">Kenaikan kelas</x-ui.button>
            <x-ui.button href="{{ route('admin.kelas.siswa.template', $kelas) }}" variant="secondary">Unduh template siswa</x-ui.button>
            <button type="button" class="btn btn-secondary" data-modal-open="import-siswa-kelas">Import siswa ke kelas ini</button>
        </div>
    </div>

    @if ($siswa->isEmpty())
        <x-ui.empty-state
            title="Belum ada siswa"
            description="Siswa di kelas ini akan muncul setelah ditambahkan atau diimpor."
        />
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>NISN</th>
                    <th>Status</th>
                </tr>
            </x-slot:thead>
            @foreach ($siswa as $item)
                <tr>
                    <td>{{ $item->nis ?? '—' }}</td>
                    <td>{{ $item->nama }}</td>
                    <td>{{ $item->nisn ?? '—' }}</td>
                    <td>
                        <x-ui.badge :tone="SiswaStatus::tone($item->status_siswa)">
                            {{ SiswaStatus::label($item->status_siswa) }}
                        </x-ui.badge>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$siswa" />
    @endif

    <x-ui.modal id="import-siswa-kelas" title="Import data siswa">
        <p>
            Unggah file Excel (.xlsx) sesuai template. Siswa akan terdaftar di kelas
            <strong>{{ $kelas->nama }}</strong> ({{ $kelas->tahunAjaran?->nama ?? '—' }}).
        </p>

        <form id="import-siswa-kelas-form" method="POST" action="{{ route('admin.kelas.siswa.import', $kelas) }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="import-siswa-kelas-file" class="field-label">File Excel</label>
                <input id="import-siswa-kelas-file" type="file" name="file" accept=".xlsx,.xls" class="field-control" required>
            </div>
        </form>

        <x-slot:actions>
            <form method="dialog">
                <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
            </form>
            <x-ui.button type="submit" form="import-siswa-kelas-form">Import</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal id="delete-kelas-show" title="Hapus kelas?">
        <p>
            Menghapus <strong>{{ $kelas->nama }}</strong> akan berdampak:
        </p>
        <ul>
            <li>Penghapusan bersifat permanen; nama kelas bisa dipakai lagi.</li>
            <li>Jika masih memiliki siswa, penghapusan akan ditolak.</li>
        </ul>

        <x-slot:actions>
            <form method="dialog">
                <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
            </form>
            <form method="POST" action="{{ route('admin.kelas.destroy', $kelas) }}">
                @csrf
                @method('DELETE')
                <x-ui.button variant="danger" type="submit">Hapus kelas</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.modal>
@endsection
