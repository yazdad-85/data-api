@extends('layouts.admin')

@php
    use App\Support\Master\SiswaStatus;

    $activeFilters = collect([
        $filters['q'],
        $filters['lembaga_id'],
        $filters['tahun_ajaran_id'],
        $filters['tahun'],
        $filters['status'],
        $filters['status_siswa'],
    ])->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
@endphp

@section('title', $title)

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / {{ $title }}
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">{{ $title }}</h1>
            <p class="page-header__description">{{ $description }}</p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.monitoring.'.$resource.'.export', request()->except('page')) }}" variant="secondary">Export Excel</x-ui.button>
            <x-ui.button href="{{ route('admin.monitoring.guru') }}" variant="{{ $resource === 'guru' ? 'primary' : 'secondary' }}">Guru</x-ui.button>
            <x-ui.button href="{{ route('admin.monitoring.siswa') }}" variant="{{ $resource === 'siswa' ? 'primary' : 'secondary' }}">Siswa</x-ui.button>
            <x-ui.button href="{{ route('admin.monitoring.karyawan') }}" variant="{{ $resource === 'karyawan' ? 'primary' : 'secondary' }}">Karyawan</x-ui.button>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.monitoring.'.$resource) }}" class="monitoring-filter" role="search">
        <select name="lembaga_id" class="field-control" aria-label="Filter lembaga">
            <option value="" @selected($filters['lembaga_id'] === '')>Semua lembaga</option>
            @foreach ($lembagas as $lembaga)
                <option value="{{ $lembaga->id }}" @selected($filters['lembaga_id'] === $lembaga->id)>
                    {{ $lembaga->nama }}
                </option>
            @endforeach
        </select>

        @if ($resource === 'siswa')
            <select name="tahun_ajaran_id" class="field-control" aria-label="Filter tahun ajaran">
                <option value="" @selected($filters['tahun_ajaran_id'] === '')>Semua tahun ajaran</option>
                @foreach ($tahunAjarans as $tahunAjaran)
                    <option value="{{ $tahunAjaran->id }}" @selected($filters['tahun_ajaran_id'] === $tahunAjaran->id)>
                        {{ $tahunAjaran->nama }} · {{ $tahunAjaran->lembaga->nama ?? '—' }}
                    </option>
                @endforeach
            </select>
            <select name="status_siswa" class="field-control" aria-label="Filter status siswa">
                <option value="" @selected($filters['status_siswa'] === '')>Semua status siswa</option>
                @foreach (SiswaStatus::ALL as $status)
                    <option value="{{ $status }}" @selected($filters['status_siswa'] === $status)>
                        {{ SiswaStatus::label($status) }}
                    </option>
                @endforeach
            </select>
        @else
            <select name="tahun" class="field-control" aria-label="Filter tahun masuk">
                <option value="" @selected($filters['tahun'] === '')>Semua tahun masuk</option>
                @foreach ($years as $year)
                    <option value="{{ $year }}" @selected($filters['tahun'] === (string) $year)>
                        {{ $year }}
                    </option>
                @endforeach
            </select>
        @endif

        <select name="status" class="field-control" aria-label="Filter status aktif">
            <option value="" @selected($filters['status'] === '')>Semua status aktif</option>
            <option value="aktif" @selected($filters['status'] === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected($filters['status'] === 'nonaktif')>Nonaktif</option>
        </select>

        <input
            type="search"
            name="q"
            value="{{ $filters['q'] }}"
            placeholder="@if ($resource === 'siswa') Cari nama, NIS, NISN @elseif ($resource === 'guru') Cari nama, NIY, NUPTK @else Cari nama, NIK, jabatan @endif"
            class="field-control"
            aria-label="Cari data"
        >

        <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
        @if ($activeFilters)
            <x-ui.button href="{{ $resetRoute }}" variant="ghost">Reset</x-ui.button>
        @endif
    </form>

    <div class="monitoring-summary">
        <div>
            <span class="monitoring-summary__label">Hasil tampil</span>
            <strong class="monitoring-summary__value font-display">{{ $rows->total() }}</strong>
        </div>
        <p>
            Halaman ini read-only untuk super admin. Perubahan data tetap dilakukan oleh admin lembaga melalui menu operasional masing-masing.
        </p>
    </div>

    @if ($rows->isEmpty())
        <x-ui.empty-state
            title="Data tidak ditemukan"
            description="Tidak ada data yang cocok dengan filter saat ini."
        />
    @else
        <x-ui.table>
            <x-slot:thead>
                @if ($resource === 'guru')
                    <tr>
                        <th>Nama</th>
                        <th>Lembaga</th>
                        <th>NIY</th>
                        <th>Tahun masuk</th>
                        <th>Status kepegawaian</th>
                        <th>Status</th>
                    </tr>
                @elseif ($resource === 'siswa')
                    <tr>
                        <th>Nama</th>
                        <th>Lembaga</th>
                        <th>NIS/NISN</th>
                        <th>Tahun ajaran</th>
                        <th>Kelas</th>
                        <th>Status siswa</th>
                        <th>Status aktif</th>
                    </tr>
                @else
                    <tr>
                        <th>Nama</th>
                        <th>Lembaga</th>
                        <th>NIK</th>
                        <th>Tahun masuk</th>
                        <th>Jabatan</th>
                        <th>Status</th>
                    </tr>
                @endif
            </x-slot:thead>

            @foreach ($rows as $row)
                @if ($resource === 'guru')
                    <tr>
                        <td>{{ $row->nama }}</td>
                        <td>
                            @if ($row->lembaga)
                                <a href="{{ route('admin.lembaga.show', $row->lembaga) }}">{{ $row->lembaga->nama }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->niy ?? '—' }}</td>
                        <td>{{ $row->tahun_masuk ?? '—' }}</td>
                        <td>{{ $row->status_kepegawaian ?? '—' }}</td>
                        <td>
                            <x-ui.badge tone="{{ $row->is_active ? 'ok' : 'neutral' }}">
                                {{ $row->is_active ? 'Aktif' : 'Nonaktif' }}
                            </x-ui.badge>
                        </td>
                    </tr>
                @elseif ($resource === 'siswa')
                    <tr>
                        <td>{{ $row->nama }}</td>
                        <td>
                            @if ($row->lembaga)
                                <a href="{{ route('admin.lembaga.show', $row->lembaga) }}">{{ $row->lembaga->nama }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{ $row->nis ?? '—' }}
                            @if ($row->nisn)
                                <span class="text-muted">/ {{ $row->nisn }}</span>
                            @endif
                        </td>
                        <td>{{ $row->tahunAjaran->nama ?? '—' }}</td>
                        <td>{{ $row->kelas->nama ?? '—' }}</td>
                        <td>
                            <x-ui.badge :tone="SiswaStatus::tone($row->status_siswa)">
                                {{ SiswaStatus::label($row->status_siswa) }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <x-ui.badge tone="{{ $row->is_active ? 'ok' : 'neutral' }}">
                                {{ $row->is_active ? 'Aktif' : 'Nonaktif' }}
                            </x-ui.badge>
                        </td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $row->nama }}</td>
                        <td>
                            @if ($row->lembaga)
                                <a href="{{ route('admin.lembaga.show', $row->lembaga) }}">{{ $row->lembaga->nama }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->nik_pegawai ?? '—' }}</td>
                        <td>{{ $row->tahun_masuk ?? '—' }}</td>
                        <td>{{ $row->jabatan ?? '—' }}</td>
                        <td>
                            <x-ui.badge tone="{{ $row->is_active ? 'ok' : 'neutral' }}">
                                {{ $row->is_active ? 'Aktif' : 'Nonaktif' }}
                            </x-ui.badge>
                        </td>
                    </tr>
                @endif
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$rows" />
    @endif
@endsection
