@extends('layouts.admin')

@section('title', 'Karyawan')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Karyawan
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Karyawan</h1>
            <p class="page-header__description">
                Kelola data karyawan lembaga Anda.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.karyawan.create') }}">Tambah karyawan</x-ui.button>
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

    <form method="GET" action="{{ route('admin.karyawan.index') }}" class="toolbar" role="search">
        <input
            type="search"
            name="q"
            value="{{ $q }}"
            placeholder="Cari nama atau NIK karyawan"
            class="field-control"
            aria-label="Cari karyawan"
        >
        <x-ui.button type="submit" variant="secondary">Cari</x-ui.button>
        @if ($q !== '')
            <x-ui.button href="{{ route('admin.karyawan.index') }}" variant="ghost">Reset</x-ui.button>
        @endif
    </form>

    @if ($karyawans->isEmpty())
        @php
            $emptyDescription = $q !== ''
                ? "Tidak ada karyawan yang cocok dengan pencarian \"{$q}\"."
                : 'Mulai dengan menambahkan karyawan pertama.';
        @endphp
        <x-ui.empty-state title="Belum ada karyawan" :description="$emptyDescription">
            <x-ui.button href="{{ route('admin.karyawan.create') }}">Tambah karyawan</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>Nama</th>
                    <th>NIK pegawai</th>
                    <th>Jabatan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </x-slot:thead>
            @foreach ($karyawans as $karyawan)
                <tr>
                    <td>{{ $karyawan->nama }}</td>
                    <td>{{ $karyawan->nik_pegawai ?? '—' }}</td>
                    <td>{{ $karyawan->jabatan ?? '—' }}</td>
                    <td>
                        @if ($karyawan->is_active)
                            <x-ui.badge tone="ok">Aktif</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="table-actions">
                            <x-ui.button href="{{ route('admin.karyawan.show', $karyawan) }}" class="btn-sm">
                                Detail
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('admin.karyawan.edit', $karyawan) }}"
                                variant="secondary"
                                class="btn-sm"
                            >
                                Ubah
                            </x-ui.button>
                            @if ($karyawan->is_active)
                                <form method="POST" action="{{ route('admin.karyawan.deactivate', $karyawan) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Nonaktifkan</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.karyawan.activate', $karyawan) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Aktifkan</button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-danger btn-sm" data-modal-open="delete-karyawan-{{ $karyawan->id }}">
                                Hapus
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$karyawans" />
    @endif

    @foreach ($karyawans as $karyawan)
        <x-ui.modal id="delete-karyawan-{{ $karyawan->id }}" title="Hapus karyawan?">
            <p>
                Menghapus <strong>{{ $karyawan->nama }}</strong> akan berdampak:
            </p>
            <ul>
                <li>Karyawan tidak lagi muncul pada daftar dan pencarian.</li>
                <li>Data tetap tersimpan (soft delete) dan dapat dipulihkan oleh operator jika diperlukan.</li>
            </ul>

            <x-slot:actions>
                <form method="dialog">
                    <x-ui.button variant="secondary" type="submit">Batal</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.karyawan.destroy', $karyawan) }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" type="submit">Hapus karyawan</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.modal>
    @endforeach
@endsection
