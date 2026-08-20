@extends('layouts.admin')

@section('title', 'Guru')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Guru
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Guru</h1>
            <p class="page-header__description">
                Kelola data guru lembaga Anda.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.guru.template') }}" variant="secondary">Unduh template</x-ui.button>
            <button type="button" class="btn btn-secondary" data-modal-open="import-guru">Import Excel</button>
            <x-ui.button href="{{ route('admin.guru.create') }}">Tambah guru</x-ui.button>
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

    <form method="GET" action="{{ route('admin.guru.index') }}" class="toolbar" role="search">
        <input
            type="search"
            name="q"
            value="{{ $q }}"
            placeholder="Cari nama, NIY, NIK, atau Peg-ID guru"
            class="field-control"
            aria-label="Cari guru"
        >
        <x-ui.button type="submit" variant="secondary">Cari</x-ui.button>
        @if ($q !== '')
            <x-ui.button href="{{ route('admin.guru.index') }}" variant="ghost">Reset</x-ui.button>
        @endif
    </form>

    @if ($gurus->isEmpty())
        @php
            $emptyDescription = $q !== ''
                ? "Tidak ada guru yang cocok dengan pencarian \"{$q}\"."
                : 'Mulai dengan menambahkan guru pertama.';
        @endphp
        <x-ui.empty-state title="Belum ada guru" :description="$emptyDescription">
            <x-ui.button href="{{ route('admin.guru.create') }}">Tambah guru</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>Nama</th>
                    <th>NIY</th>
                    <th>NIK</th>
                    <th>Status kepegawaian</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </x-slot:thead>
            @foreach ($gurus as $guru)
                <tr>
                    <td>{{ $guru->nama }}</td>
                    <td>{{ $guru->niy ?? '—' }}</td>
                    <td>{{ $guru->nik ?? '—' }}</td>
                    <td>{{ $guru->status_kepegawaian ?? '—' }}</td>
                    <td>
                        @if ($guru->is_active)
                            <x-ui.badge tone="ok">Aktif</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="table-actions">
                            <x-ui.button href="{{ route('admin.guru.show', $guru) }}" class="btn-sm">
                                Detail
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('admin.guru.edit', $guru) }}"
                                variant="secondary"
                                class="btn-sm"
                            >
                                Ubah
                            </x-ui.button>
                            @if ($guru->is_active)
                                <form method="POST" action="{{ route('admin.guru.deactivate', $guru) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Nonaktifkan</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.guru.activate', $guru) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Aktifkan</button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-danger btn-sm" data-modal-open="delete-guru-{{ $guru->id }}">
                                Hapus
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$gurus" />
    @endif

    @foreach ($gurus as $guru)
        <x-ui.modal id="delete-guru-{{ $guru->id }}" title="Hapus guru?">
            <p>
                Menghapus <strong>{{ $guru->nama }}</strong> akan berdampak:
            </p>
            <ul>
                <li>Guru tidak lagi muncul pada daftar dan pencarian.</li>
                <li>Data tetap tersimpan (soft delete) dan dapat dipulihkan oleh operator jika diperlukan.</li>
            </ul>

            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.guru.destroy', $guru) }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" type="submit">Hapus guru</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.modal>
    @endforeach

    <x-ui.modal id="import-guru" title="Import data guru">
        <p>Unggah file Excel (.xlsx) sesuai template. NIY akan digenerate otomatis.</p>

        <form id="import-guru-form" method="POST" action="{{ route('admin.guru.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="import-file" class="field-label">File Excel</label>
                <input id="import-file" type="file" name="file" accept=".xlsx,.xls" class="field-control" required>
            </div>
        </form>

        <x-slot:actions>
            <form method="dialog">
                <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
            </form>
            <x-ui.button type="submit" form="import-guru-form">Import</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
@endsection
