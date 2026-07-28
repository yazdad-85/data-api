@extends('layouts.admin')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
    <div class="dashboard">
        <header class="dashboard__intro">
            <h1 class="font-display">Dashboard</h1>
            @if ($stats['role'] === 'super_admin')
                <p>Ringkasan lembaga, API client, dan master data di seluruh sistem.</p>
            @else
                <p>
                    Urutan pengisian data untuk
                    <strong>{{ $stats['lembaga_nama'] ?? 'lembaga Anda' }}</strong>.
                </p>
            @endif
        </header>

        @if ($stats['role'] === 'super_admin')
            @if ($stats['lembaga_aktif'] === 0 && $stats['lembaga_nonaktif'] === 0)
                <x-ui.empty-state
                    title="Belum ada lembaga"
                    description="Dashboard siap. Kelola lembaga dari menu Lembaga. API client menyusul di Milestone 5b."
                />
            @endif

            <section class="dashboard-command">
                <div>
                    <p class="dashboard-command__eyebrow">Super admin overview</p>
                    <h2 class="dashboard-command__title font-display">Pantau kesiapan data semua lembaga</h2>
                    <p class="dashboard-command__body">
                        Lihat total master data, temukan lembaga yang belum lengkap, dan buka monitoring read-only untuk guru, siswa, atau karyawan.
                    </p>
                </div>
                <div class="dashboard-command__actions">
                    <x-ui.button href="{{ route('admin.monitoring.siswa') }}">Monitoring siswa</x-ui.button>
                    <x-ui.button href="{{ route('admin.monitoring.guru') }}" variant="secondary">Guru</x-ui.button>
                    <x-ui.button href="{{ route('admin.monitoring.karyawan') }}" variant="secondary">Karyawan</x-ui.button>
                </div>
            </section>

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
                    <span class="dashboard-metric__label">Siswa</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['siswa'] }}</span>
                    <span class="dashboard-metric__hint">{{ $stats['siswa_aktif'] }} aktif</span>
                </div>
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">Karyawan</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['karyawan'] }}</span>
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
                                $isIncomplete = $lembaga->guru_count === 0 || $lembaga->siswa_count === 0 || $lembaga->karyawan_count === 0;
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
                                    <a href="{{ route('admin.monitoring.siswa', ['lembaga_id' => $lembaga->id]) }}">{{ $lembaga->siswa_count }}</a>
                                    <div class="table-subtext">{{ $lembaga->siswa_aktif_count }} aktif</div>
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
            <ol class="dashboard-checklist">
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
                    <li class="dashboard-checklist__item">
                        <div class="dashboard-checklist__main">
                            <span class="dashboard-checklist__step" aria-hidden="true">{{ $item['step'] }}</span>
                            <div>
                                <a
                                    class="dashboard-checklist__label"
                                    href="{{ route($route) }}"
                                >{{ $item['label'] }}</a>
                                <p class="dashboard-checklist__hint">
                                    @if ($count === 0)
                                        Belum ada data — mulai dari menu {{ $item['label'] }}.
                                    @else
                                        {{ $count }} data tercatat.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="dashboard-checklist__meta">
                            <span class="dashboard-checklist__count font-display">{{ $count }}</span>
                            @if ($count === 0)
                                <x-ui.badge tone="warn">Kosong</x-ui.badge>
                            @else
                                <x-ui.badge tone="ok">Ada data</x-ui.badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
@endsection
