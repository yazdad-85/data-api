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
            <div class="dashboard-metrics" role="list">
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
                </div>
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">Siswa</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['siswa'] }}</span>
                </div>
                <div class="dashboard-metric" role="listitem">
                    <span class="dashboard-metric__label">Karyawan</span>
                    <span class="dashboard-metric__value font-display">{{ $stats['karyawan'] }}</span>
                </div>
            </div>
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
