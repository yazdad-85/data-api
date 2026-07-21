@extends('layouts.admin')

@section('title', 'Lembaga')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Lembaga
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Lembaga</h1>
            <p class="page-header__description">
                Kelola lembaga yang terdaftar di Pusat Data.
                Admin lembaga dikelola dari halaman <strong>Detail</strong> tiap lembaga.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.lembaga.create') }}">Tambah lembaga</x-ui.button>
        </div>
    </div>

    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    <form method="GET" action="{{ route('admin.lembaga.index') }}" class="toolbar" role="search">
        <input
            type="search"
            name="q"
            value="{{ $q }}"
            placeholder="Cari kode atau nama lembaga"
            class="field-control"
            aria-label="Cari lembaga"
        >
        <x-ui.button type="submit" variant="secondary">Cari</x-ui.button>
        @if ($q !== '')
            <x-ui.button href="{{ route('admin.lembaga.index') }}" variant="ghost">Reset</x-ui.button>
        @endif
    </form>

    @if ($lembagas->isEmpty())
        @php
            $emptyDescription = $q !== ''
                ? "Tidak ada lembaga yang cocok dengan pencarian \"{$q}\"."
                : 'Mulai dengan menambahkan lembaga pertama.';
        @endphp
        <x-ui.empty-state title="Belum ada lembaga" :description="$emptyDescription">
            <x-ui.button href="{{ route('admin.lembaga.create') }}">Tambah lembaga</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Jenis</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </x-slot:thead>
            @foreach ($lembagas as $lembaga)
                <tr>
                    <td>{{ $lembaga->kode }}</td>
                    <td>{{ $lembaga->nama }}</td>
                    <td>{{ $lembaga->jenis ?? '—' }}</td>
                    <td>
                        @if ($lembaga->is_active)
                            <x-ui.badge tone="ok">Aktif</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="table-actions">
                            <x-ui.button
                                href="{{ route('admin.lembaga.show', $lembaga) }}"
                                class="btn-sm"
                            >
                                Detail
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('admin.lembaga.edit', $lembaga) }}"
                                variant="secondary"
                                class="btn-sm"
                            >
                                Ubah
                            </x-ui.button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$lembagas" />
    @endif
@endsection
