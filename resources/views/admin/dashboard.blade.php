@extends('layouts.admin')

@php
    use App\Support\Master\SiswaStatus;

    $trend = $stats['trend_master'];
    $trendColors = ['Siswa' => 'blue', 'Guru' => 'emerald', 'Karyawan' => 'amber'];
    $academic = $stats['tahun_ajaran_analysis'];
    $academicColors = ['Total' => 'slate', 'Aktif' => 'emerald', 'Mutasi masuk' => 'blue', 'Mutasi keluar' => 'red', 'Lulus' => 'amber'];
    $selectedLembagaId = $stats['selected_lembaga_id'] ?? '';
    $lembagaFilter = $stats['role'] === 'super_admin' && $selectedLembagaId !== '' ? ['lembaga_id' => $selectedLembagaId] : [];
    $chartValue = static function (int $value): string {
        if ($value >= 1000000) {
            return rtrim(rtrim(number_format($value / 1000000, $value >= 10000000 ? 0 : 1, ',', '.'), '0'), ',').'jt';
        }

        if ($value >= 1000) {
            return rtrim(rtrim(number_format($value / 1000, $value >= 10000 ? 0 : 1, ',', '.'), '0'), ',').'rb';
        }

        return (string) $value;
    };
    $statusTotal = max(1, array_sum($stats['siswa_status']));
    $statusItems = [
        SiswaStatus::AKTIF,
        SiswaStatus::MUTASI_MASUK,
        SiswaStatus::MUTASI_KELUAR,
        SiswaStatus::LULUS,
        SiswaStatus::CALON,
    ];
    $familyLabels = $stats['status_keluarga_labels'];
    $familyShortLabels = [
        'Yatim' => 'Yatim',
        'Piatu' => 'Piatu',
        'Yatim Piatu' => 'Yatim Piatu',
        'Anak Guru, Staff, dan Karyawan' => 'Anak guru/staff/karyawan',
        'Belum diisi' => 'Belum diisi',
    ];
    $familyRows = $stats['status_keluarga_per_kelas'];
    $familyRowsWithStudents = $familyRows->filter(fn (array $row): bool => $row['total'] > 0)->values();
    $familyEmptyRowsCount = $familyRows->count() - $familyRowsWithStudents->count();
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
                    <x-ui.button href="{{ route('admin.laporan.siswa', $lembagaFilter) }}">Laporan siswa</x-ui.button>
                    <x-ui.button href="{{ route('admin.monitoring.siswa', $lembagaFilter) }}" variant="secondary">Monitoring</x-ui.button>
                @else
                    <x-ui.button href="{{ route('admin.siswa.create') }}">Tambah siswa</x-ui.button>
                    <x-ui.button href="{{ route('admin.laporan.siswa') }}" variant="secondary">Laporan siswa</x-ui.button>
                @endif
            </div>
        </header>

        @if ($stats['role'] === 'super_admin')
            <form method="GET" action="{{ route('admin.dashboard') }}" class="dashboard-filter dashboard-filter--super">
                <select name="lembaga_id" class="field-control" aria-label="Filter lembaga dashboard">
                    <option value="" @selected($selectedLembagaId === '')>Semua lembaga</option>
                    @foreach ($stats['lembaga_options'] as $lembaga)
                        <option value="{{ $lembaga->id }}" @selected($selectedLembagaId === $lembaga->id)>
                            {{ $lembaga->nama }}
                        </option>
                    @endforeach
                </select>
                <x-ui.button type="submit" variant="secondary">Terapkan</x-ui.button>
                @if ($selectedLembagaId !== '')
                    <x-ui.button href="{{ route('admin.dashboard') }}" variant="secondary">Semua lembaga</x-ui.button>
                @endif
            </form>

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
                <a class="dashboard-metric" href="{{ route('admin.laporan.siswa', array_merge($lembagaFilter, ['status_siswa' => SiswaStatus::MUTASI_MASUK])) }}" role="listitem">
                    <span class="dashboard-metric__label">Mutasi masuk</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['siswa_mutasi_masuk'] }}</span>
                    <span class="dashboard-metric__hint">Siswa pindahan</span>
                </a>
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
                <a class="dashboard-metric" href="{{ route('admin.laporan.siswa', ['status_siswa' => SiswaStatus::MUTASI_MASUK]) }}" role="listitem">
                    <span class="dashboard-metric__label">Mutasi masuk</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['siswa_mutasi_masuk'] }}</span>
                    <span class="dashboard-metric__hint">Siswa pindahan</span>
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

                <div class="dashboard-chart" style="--chart-columns: {{ count($trend['labels']) }};" aria-label="Grafik perkembangan master data">
                    @foreach ($trend['labels'] as $index => $label)
                        <div class="dashboard-chart__group">
                            <div class="dashboard-chart__bars">
                                @foreach ($trend['series'] as $name => $values)
                                    @php
                                        $value = $values[$index] ?? 0;
                                        $height = max(4, (int) round(($value / $trend['max']) * 88));
                                    @endphp
                                    <div
                                        class="dashboard-chart__bar dashboard-chart__bar--{{ $trendColors[$name] }}"
                                        style="--bar-height: {{ $height }}%;"
                                        title="{{ $name }}: {{ $value }}"
                                    >
                                        <span>{{ $chartValue((int) $value) }}</span>
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
                        <a class="dashboard-status" href="{{ route('admin.laporan.siswa', array_merge($lembagaFilter, ['status_siswa' => $status])) }}">
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

        <section class="dashboard-panel dashboard-panel--wide">
            <div class="dashboard-panel__header">
                <div>
                    <h2 class="dashboard-panel__title font-display">Status keluarga per kelas</h2>
                    <p class="dashboard-panel__description">
                        Rekap siswa aktif berdasarkan status keluarga pada setiap kelas.
                    </p>
                </div>
            </div>

            <div class="dashboard-family-summary" role="list">
                @foreach ($familyLabels as $status)
                    <div class="dashboard-family-card" role="listitem">
                        <span>{{ $familyShortLabels[$status] }}</span>
                        <strong class="font-display">{{ $stats['status_keluarga_summary'][$status] ?? 0 }}</strong>
                    </div>
                @endforeach
            </div>

            @if ($stats['status_keluarga_per_kelas']->isEmpty())
                <p class="dashboard-panel__description">Belum ada kelas untuk direkap.</p>
            @else
                <div class="dashboard-family-toolbar">
                    <strong>Kelas dengan siswa aktif</strong>
                    <span>
                        {{ $familyRowsWithStudents->count() }} kelas ditampilkan
                        @if ($familyEmptyRowsCount > 0)
                            · {{ $familyEmptyRowsCount }} kelas kosong disembunyikan
                        @endif
                    </span>
                </div>

                @if ($familyRowsWithStudents->isEmpty())
                    <p class="dashboard-panel__description">Belum ada kelas dengan siswa aktif.</p>
                @else
                    @include('admin.dashboard.family-status-table', ['rows' => $familyRowsWithStudents])
                @endif

                @if ($familyEmptyRowsCount > 0)
                    <details class="dashboard-disclosure">
                        <summary>
                            <span>Lihat semua kelas</span>
                            <strong>{{ $familyRows->count() }} kelas</strong>
                        </summary>
                        @include('admin.dashboard.family-status-table', ['rows' => $familyRows])
                    </details>
                @endif
            @endif
        </section>

        <section class="dashboard-panel dashboard-panel--wide">
            <div class="dashboard-panel__header">
                <div>
                    <h2 class="dashboard-panel__title font-display">
                        {{ $academic['mode'] === 'single' ? 'Komposisi siswa tahun ajaran' : 'Perbandingan tahun ajaran' }}
                    </h2>
                    <p class="dashboard-panel__description">
                        {{ $academic['mode'] === 'single'
                            ? 'Lihat total, aktif, mutasi masuk, mutasi keluar, dan lulus pada tahun ajaran terpilih.'
                            : 'Bandingkan total dan status siswa antar tahun ajaran.' }}
                    </p>
                </div>
                <form method="GET" action="{{ route('admin.dashboard') }}" class="dashboard-filter">
                    @if ($selectedLembagaId !== '')
                        <input type="hidden" name="lembaga_id" value="{{ $selectedLembagaId }}">
                    @endif
                    <select name="tahun_ajaran_id" class="field-control" aria-label="Filter tahun ajaran dashboard">
                        <option value="" @selected($academic['selected_id'] === '')>Semua tahun ajaran</option>
                        @foreach ($stats['tahun_ajaran_options'] as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($academic['selected_id'] === $tahunAjaran->id)>
                                {{ $tahunAjaran->nama }}@if ($stats['role'] === 'super_admin') · {{ $tahunAjaran->lembaga->nama ?? '—' }}@endif
                            </option>
                        @endforeach
                    </select>
                    <x-ui.button type="submit" variant="secondary">Terapkan</x-ui.button>
                </form>
            </div>

            @if ($academic['labels'] === [])
                <p class="dashboard-panel__description">Belum ada tahun ajaran untuk dibandingkan.</p>
            @else
                @if ($academic['selected_summary'] !== null)
                    <div class="dashboard-academic-summary" role="list">
                        @foreach (['total' => 'Total siswa', 'aktif' => 'Aktif', 'mutasi_masuk' => 'Mutasi masuk', 'mutasi_keluar' => 'Mutasi keluar', 'lulus' => 'Lulus'] as $key => $label)
                            <div class="dashboard-academic-card" role="listitem">
                                <span>{{ $label }}</span>
                                <strong class="font-display">{{ $academic['selected_summary'][$key] }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="dashboard-chart dashboard-chart--academic" style="--chart-columns: {{ max(1, count($academic['labels'])) }};" aria-label="Grafik perbandingan siswa per tahun ajaran">
                    @foreach ($academic['labels'] as $index => $label)
                        <div class="dashboard-chart__group">
                            <div class="dashboard-chart__bars">
                                @foreach ($academic['series'] as $name => $values)
                                    @php
                                        $value = $values[$index] ?? 0;
                                        $height = max(4, (int) round(($value / $academic['max']) * 88));
                                    @endphp
                                    <div
                                        class="dashboard-chart__bar dashboard-chart__bar--{{ $academicColors[$name] }}"
                                        style="--bar-height: {{ $height }}%;"
                                        title="{{ $name }}: {{ $value }}"
                                    >
                                        <span>{{ $chartValue((int) $value) }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <span class="dashboard-chart__label">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="dashboard-chart__legend dashboard-chart__legend--inline">
                    @foreach ($academic['series'] as $name => $values)
                        <span class="dashboard-chart__legend-item dashboard-chart__legend-item--{{ $academicColors[$name] }}">{{ $name }}</span>
                    @endforeach
                </div>
            @endif
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
        @endif
    </div>
@endsection
