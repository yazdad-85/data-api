@extends('layouts.admin')

@php
    use App\Support\Master\SiswaStatus;

    $trend = $stats['trend_master'];
    $trendColors = ['Siswa' => 'brand', 'Guru' => 'ok', 'Karyawan' => 'warn'];
    $statusTotal = max(1, array_sum($stats['siswa_status']));
    $statusItems = [
        SiswaStatus::AKTIF,
        SiswaStatus::MUTASI_MASUK,
        SiswaStatus::MUTASI_KELUAR,
        SiswaStatus::LULUS,
        SiswaStatus::CALON,
    ];
@endphp

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
    <div class="dashboard">
        <header class="dashboard-hero">
            <div>
                <p class="dashboard-hero__eyebrow">{{ $stats['role'] === 'super_admin' ? 'Super admin overview' : 'Ringkasan lembaga' }}</p>
                <h1 class="dashboard-hero__title font-display">
                    {{ $stats['role'] === 'super_admin' ? 'Pusat kendali data lembaga' : ($stats['lembaga_nama'] ?? 'Dashboard lembaga') }}
                </h1>
                <p class="dashboard-hero__body">
                    {{ $stats['role'] === 'super_admin'
                        ? 'Pantau kesiapan data seluruh lembaga, tren master data, dan riwayat siswa dalam satu layar.'
                        : 'Pantau data aktif, riwayat siswa, dan perkembangan master data lembaga Anda.' }}
                </p>
            </div>
            <div class="dashboard-hero__actions">
                @if ($stats['role'] === 'super_admin')
                    <x-ui.button href="{{ route('admin.laporan.siswa') }}">Laporan siswa</x-ui.button>
                    <x-ui.button href="{{ route('admin.monitoring.siswa') }}" variant="secondary">Monitoring</x-ui.button>
                @else
                    <x-ui.button href="{{ route('admin.siswa.create') }}">Tambah siswa</x-ui.button>
                    <x-ui.button href="{{ route('admin.laporan.siswa') }}" variant="secondary">Laporan siswa</x-ui.button>
                @endif
            </div>
        </header>

        @if ($stats['role'] === 'super_admin')
            @if ($stats['lembaga_aktif'] === 0 && $stats['lembaga_nonaktif'] === 0)
                <x-ui.empty-state
                    title="Belum ada lembaga"
                    description="Dashboard siap. Kelola lembaga dari menu Lembaga. API client menyusul di Milestone 5b."
                />
            @endif

            <div class="dashboard-metrics dashboard-metrics--super" role="list">
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">Lembaga aktif</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['lembaga_aktif'] }}</span>
                    <x-ui.badge tone="ok">Aktif</x-ui.badge>
                </div>
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">Lembaga nonaktif</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['lembaga_nonaktif'] }}</span>
                    <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                </div>
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">API client aktif</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['api_client_aktif'] }}</span>
                    <x-ui.badge tone="brand">API</x-ui.badge>
                </div>
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">Guru</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['guru'] }}</span>
                    <span class="dashboard-metric__hint">{{ $stats['guru_aktif'] }} aktif</span>
                </div>
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">Siswa aktif</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['siswa'] }}</span>
                    <span class="dashboard-metric__hint">{{ $stats['total_siswa'] }} total data</span>
                </div>
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">Karyawan aktif</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['karyawan_aktif'] }}</span>
                    <span class="dashboard-metric__hint">{{ $stats['karyawan_aktif'] }} aktif</span>
                </div>
                <div class="dashboard-metric dashboard-metric--attention" role="listitem">
                    <span class="dashboard-metric__label">Perlu dilengkapi</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['lembaga_belum_lengkap'] }}</span>
                    <x-ui.badge tone="{{ $stats['lembaga_belum_lengkap'] > 0 ? 'warn' : 'ok' }}">
                        {{ $stats['lembaga_belum_lengkap'] > 0 ? 'Cek data' : 'Lengkap' }}
                    </x-ui.badge>
                </div>
            </div>
        @else
            <div class="dashboard-metrics" role="list">
                <a class="dashboard-metric" href="{{ route('admin.siswa.index', ['status_siswa' => SiswaStatus::AKTIF]) }}" role="listitem">
                    <span class="dashboard-metric__label">Siswa aktif</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['siswa'] }}</span>
                    <span class="dashboard-metric__hint">{{ $stats['total_siswa'] }} total data siswa</span>
                </a>
                <a class="dashboard-metric" href="{{ route('admin.guru.index') }}" role="listitem">
                    <span class="dashboard-metric__label">Guru</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['guru'] }}</span>
                    <span class="dashboard-metric__hint">Data tenaga pendidik</span>
                </a>
                <a class="dashboard-metric" href="{{ route('admin.karyawan.index') }}" role="listitem">
                    <span class="dashboard-metric__label">Karyawan</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['karyawan_aktif'] }}</span>
                    <span class="dashboard-metric__hint">{{ $stats['karyawan'] }} total data</span>
                </a>
                <a class="dashboard-metric" href="{{ route('admin.kelas.index') }}" role="listitem">
                    <span class="dashboard-metric__label">Kelas</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['kelas'] }}</span>
                    <span class="dashboard-metric__hint">{{ $stats['tahun_ajaran'] }} tahun ajaran</span>
                </a>
                <a class="dashboard-metric dashboard-metric--attention" href="{{ route('admin.laporan.siswa', ['status_siswa' => SiswaStatus::MUTASI_KELUAR]) }}" role="listitem">
                    <span class="dashboard-metric__label">Mutasi keluar</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['siswa_mutasi_keluar'] }}</span>
                    <span class="dashboard-metric__hint">{{ $stats['siswa_lulus'] }} lulus</span>
                </a>
            </div>
        @endif

        <section class="dashboard-grid">
            <div class="dashboard-panel dashboard-panel--wide">
                <div class="dashboard-panel__header">
                    <div>
                        <h2 class="dashboard-panel__title font-display">Perkembangan data</h2>
                        <p class="dashboard-panel__description">Penambahan siswa, guru, dan karyawan dalam 6 bulan terakhir.</p>
                    </div>
                    <div class="dashboard-chart__legend">
                        @foreach ($trend['series'] as $name => $values)
                            <span class="dashboard-chart__legend-item dashboard-chart__legend-item--{{ $trendColors[$name] }}">{{ $name }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="dashboard-chart" aria-label="Grafik perkembangan master data">
                    @foreach ($trend['labels'] as $index => $label)
                        <div class="dashboard-chart__group">
                            <div class="dashboard-chart__bars">
                                @foreach ($trend['series'] as $name => $values)
                                    @php
                                        $value = $values[$index] ?? 0;
                                        $height = max(4, (int) round(($value / $trend['max']) * 100));
                                    @endphp
                                    <div
                                        class="dashboard-chart__bar dashboard-chart__bar--{{ $trendColors[$name] }}"
                                        style="--bar-height: {{ $height }}%;"
                                        title="{{ $name }}: {{ $value }}"
                                    >
                                        <span>{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <span class="dashboard-chart__label">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="dashboard-panel">
                <div class="dashboard-panel__header">
                    <div>
                        <h2 class="dashboard-panel__title font-display">Status siswa</h2>
                        <p class="dashboard-panel__description">{{ $stats['total_siswa'] }} total data siswa tercatat.</p>
                    </div>
                </div>
                <div class="dashboard-status-list">
                    @foreach ($statusItems as $status)
                        @php
                            $count = $stats['siswa_status'][$status] ?? 0;
                            $width = (int) round(($count / $statusTotal) * 100);
                        @endphp
                        <a class="dashboard-status" href="{{ route('admin.laporan.siswa', ['status_siswa' => $status]) }}">
                            <span class="dashboard-status__top">
                                <span>{{ SiswaStatus::label($status) }}</span>
                                <strong>{{ $count }}</strong>
                            </span>
                            <span class="dashboard-status__track">
                                <span class="dashboard-status__bar" style="width: {{ $width }}%;"></span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($stats['role'] === 'super_admin')
            @if ($stats['lembaga_rows']->isNotEmpty())
                <section class="dashboard-section">
                    <div class="dashboard-section__header">
                        <div>
                            <h2 class="dashboard-section__title font-display">Ringkasan per lembaga</h2>
                            <p class="dashboard-section__description">
                                Angka ini membantu super admin melihat distribusi data dan lembaga yang masih kosong.
                            </p>
                        </div>
                        <x-ui.button href="{{ route('admin.lembaga.index') }}" variant="secondary">Kelola lembaga</x-ui.button>
                    </div>

                    <x-ui.table class="dashboard-lembaga-table">
                        <x-slot:thead>
                            <tr>
                                <th>Lembaga</th>
                                <th>Tahun ajaran</th>
                                <th>Guru</th>
                                <th>Siswa</th>
                                <th>Karyawan</th>
                                <th>Kelas</th>
                                <th>Status</th>
                            </tr>
                        </x-slot:thead>
                        @foreach ($stats['lembaga_rows'] as $lembaga)
                            @php
                                $isIncomplete = $lembaga->guru_count === 0 || $lembaga->siswa_aktif_count === 0 || $lembaga->karyawan_count === 0;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.lembaga.show', $lembaga) }}">{{ $lembaga->nama }}</a>
                                    <div class="table-subtext">{{ $lembaga->kode }}</div>
                                </td>
                                <td>
                                    {{ $lembaga->tahun_ajaran_count }}
                                    <div class="table-subtext">{{ $lembaga->tahun_ajaran_aktif_count }} aktif</div>
                                </td>
                                <td>
                                    <a href="{{ route('admin.monitoring.guru', ['lembaga_id' => $lembaga->id]) }}">{{ $lembaga->guru_count }}</a>
                                    <div class="table-subtext">{{ $lembaga->guru_aktif_count }} aktif</div>
                                </td>
                                <td>
                                    <a href="{{ route('admin.laporan.siswa', ['lembaga_id' => $lembaga->id, 'status_siswa' => 'aktif']) }}">{{ $lembaga->siswa_aktif_count }}</a>
                                    <div class="table-subtext">{{ $lembaga->siswa_count }} total data</div>
                                </td>
                                <td>
                                    <a href="{{ route('admin.monitoring.karyawan', ['lembaga_id' => $lembaga->id]) }}">{{ $lembaga->karyawan_count }}</a>
                                    <div class="table-subtext">{{ $lembaga->karyawan_aktif_count }} aktif</div>
                                </td>
                                <td>{{ $lembaga->kelas_count }}</td>
                                <td>
                                    @if (! $lembaga->is_active)
                                        <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                                    @elseif ($isIncomplete)
                                        <x-ui.badge tone="warn">Belum lengkap</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="ok">Siap</x-ui.badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                </section>
            @endif
        @else
            <section class="dashboard-section">
                <div class="dashboard-section__header">
                    <div>
                        <h2 class="dashboard-section__title font-display">Kelengkapan data</h2>
                        <p class="dashboard-section__description">Ringkasan modul master yang dipakai operasional lembaga.</p>
                    </div>
                </div>

                <div class="dashboard-progress-list">
                    @foreach ($stats['urutan'] as $item)
                    @php
                        $count = $stats[$item['count_key']] ?? 0;
                        $route = match ($item['count_key']) {
                            'tahun_ajaran' => 'admin.tahun-ajaran.index',
                            'guru' => 'admin.guru.index',
                            'kelas' => 'admin.kelas.index',
                            'siswa' => 'admin.siswa.index',
                            'karyawan' => 'admin.karyawan.index',
                            default => 'admin.dashboard',
                        };
                    @endphp
                    <a class="dashboard-progress" href="{{ route($route) }}">
                        <span class="dashboard-progress__step">{{ $item['step'] }}</span>
                        <span class="dashboard-progress__main">
                            <span class="dashboard-progress__label">{{ $item['label'] }}</span>
                            <span class="dashboard-progress__hint">
                                @if ($item['count_key'] === 'siswa')
                                    {{ $count }} aktif / {{ $stats['total_siswa'] }} total
                                @else
                                    {{ $count }} data
                                @endif
                            </span>
                        </span>
                        <span class="dashboard-progress__count font-display">{{ $count }}</span>
                    </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
