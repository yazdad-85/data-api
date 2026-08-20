@extends('layouts.admin')

@php
    use App\Support\Master\PenempatanJenis;
    use App\Support\Master\SiswaStatus;

    $activeFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
    $statusCards = [
        ['label' => 'Total data siswa', 'key' => 'total', 'status' => ''],
        ['label' => 'Aktif', 'key' => 'aktif', 'status' => SiswaStatus::AKTIF],
        ['label' => 'Mutasi masuk', 'key' => 'mutasi_masuk', 'status' => SiswaStatus::MUTASI_MASUK],
        ['label' => 'Mutasi keluar', 'key' => 'mutasi_keluar', 'status' => SiswaStatus::MUTASI_KELUAR],
        ['label' => 'Lulus', 'key' => 'lulus', 'status' => SiswaStatus::LULUS],
        ['label' => 'Belum ditempatkan', 'key' => 'calon', 'status' => SiswaStatus::CALON],
    ];
@endphp

@section('title', 'Laporan Siswa')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Laporan Siswa
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Laporan Siswa</h1>
            <p class="page-header__description">
                Rekap siswa aktif dan riwayat siswa berdasarkan status mutasi masuk, mutasi keluar, dan lulus.
            </p>
        </div>
        @unless ($isSuperAdmin)
            <div class="page-header__actions">
                <x-ui.button href="{{ route('admin.siswa.index') }}" variant="secondary">Data siswa</x-ui.button>
            </div>
        @endunless
    </div>

    <div class="dashboard-metrics" role="list">
        @foreach ($statusCards as $card)
            @php
                $params = array_filter([
                    ...request()->except('page', 'status_siswa'),
                    'status_siswa' => $card['status'],
                ], fn ($value) => $value !== null && $value !== '');
            @endphp
            <a class="dashboard-metric" href="{{ route('admin.laporan.siswa', $params) }}" role="listitem">
                <span class="dashboard-metric__label">{{ $card['label'] }}</span>
                <span class="dashboard-metric__value font-display">{{ $summary[$card['key']] }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.laporan.siswa') }}" class="toolbar" role="search">
        @if ($isSuperAdmin)
            <select name="lembaga_id" class="field-control" aria-label="Filter lembaga">
                <option value="" @selected($filters['lembaga_id'] === '')>Semua lembaga</option>
                @foreach ($lembagas as $lembaga)
                    <option value="{{ $lembaga->id }}" @selected($filters['lembaga_id'] === $lembaga->id)>
                        {{ $lembaga->nama }}
                    </option>
                @endforeach
            </select>
        @endif

        <select name="tahun_ajaran_id" class="field-control" aria-label="Filter tahun ajaran">
            <option value="" @selected($filters['tahun_ajaran_id'] === '')>Semua tahun ajaran</option>
            @foreach ($tahunAjarans as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($filters['tahun_ajaran_id'] === $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}@if ($isSuperAdmin) · {{ $tahunAjaran->lembaga->nama ?? '—' }}@endif
                </option>
            @endforeach
        </select>

        <select name="kelas_id" class="field-control" aria-label="Filter kelas">
            <option value="" @selected($filters['kelas_id'] === '')>Semua kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected($filters['kelas_id'] === $kelas->id)>
                    {{ $kelas->nama }}@if ($kelas->tahunAjaran) ({{ $kelas->tahunAjaran->nama }})@endif@if ($isSuperAdmin) · {{ $kelas->lembaga->nama ?? '—' }}@endif
                </option>
            @endforeach
        </select>

        <select name="status_siswa" class="field-control" aria-label="Filter status siswa">
            <option value="" @selected($filters['status_siswa'] === '')>Semua status</option>
            @foreach (SiswaStatus::ALL as $status)
                <option value="{{ $status }}" @selected($filters['status_siswa'] === $status)>
                    {{ SiswaStatus::label($status) }}
                </option>
            @endforeach
        </select>

        <input
            type="search"
            name="q"
            value="{{ $filters['q'] }}"
            placeholder="Cari nama, NIS, NISN, ayah, atau ibu"
            class="field-control"
            aria-label="Cari siswa"
        >

        <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
        @if ($activeFilters)
            <x-ui.button href="{{ route('admin.laporan.siswa') }}" variant="ghost">Reset</x-ui.button>
        @endif
    </form>

    @if ($rows->isEmpty())
        <x-ui.empty-state
            title="Data tidak ditemukan"
            description="Tidak ada siswa yang cocok dengan filter saat ini."
        />
    @else
        <x-ui.table>
            <x-slot:thead>
                <tr>
                    <th>Nama</th>
                    @if ($isSuperAdmin)
                        <th>Lembaga</th>
                    @endif
                    <th>NIS/NISN</th>
                    <th>Kelas terakhir</th>
                    <th>Status</th>
                    <th>Tanggal status</th>
                    <th>Asal</th>
                    <th>Tujuan</th>
                    <th>Alasan</th>
                    <th>Aksi</th>
                </tr>
            </x-slot:thead>

            @foreach ($rows as $siswa)
                @php
                    $latestPlacement = $siswa->penempatans->first();
                    $kelasLabel = $siswa->kelas?->nama ?? $latestPlacement?->kelas?->nama ?? '—';
                    $tahunLabel = $siswa->tahunAjaran?->nama ?? $latestPlacement?->tahunAjaran?->nama;
                    $hasMutasiMasuk = $siswa->status_siswa === SiswaStatus::MUTASI_MASUK
                        || $siswa->penempatans->contains('jenis', PenempatanJenis::MUTASI_MASUK);
                @endphp
                <tr>
                    <td>
                        {{ $siswa->nama }}
                        @if ($hasMutasiMasuk)
                            <div class="table-subtext">Riwayat mutasi masuk</div>
                        @endif
                    </td>
                    @if ($isSuperAdmin)
                        <td>{{ $siswa->lembaga->nama ?? '—' }}</td>
                    @endif
                    <td>
                        {{ $siswa->nis ?? '—' }}
                        @if ($siswa->nisn)
                            <span class="text-muted">/ {{ $siswa->nisn }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $kelasLabel }}
                        @if ($tahunLabel)
                            <div class="table-subtext">{{ $tahunLabel }}</div>
                        @endif
                    </td>
                    <td>
                        <x-ui.badge :tone="SiswaStatus::tone($siswa->status_siswa)">
                            {{ SiswaStatus::label($siswa->status_siswa) }}
                        </x-ui.badge>
                    </td>
                    <td>{{ $siswa->status_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $siswa->status_asal ?? '—' }}</td>
                    <td>{{ $siswa->status_tujuan ?? '—' }}</td>
                    <td>{{ $siswa->status_alasan ?? '—' }}</td>
                    <td>
                        @if ($isSuperAdmin)
                            <span class="text-muted">Read-only</span>
                        @else
                            <x-ui.button href="{{ route('admin.siswa.show', $siswa) }}" class="btn-sm">Detail</x-ui.button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$rows" />
    @endif
@endsection
