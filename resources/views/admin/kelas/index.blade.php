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
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.kelas.create') }}">Tambah kelas</x-ui.button>
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
@endsection
