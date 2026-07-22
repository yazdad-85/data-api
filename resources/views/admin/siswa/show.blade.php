@extends('layouts.admin')

@section('title', $siswa->nama)

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.siswa.index') }}">Siswa</a> / {{ $siswa->nama }}
@endsection

@section('content')
    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    <div class="card">
        <div class="card__header">
            <div>
                <h1 class="card__title font-display">{{ $siswa->nama }}</h1>
                <p class="card__meta">
                    NIS <strong>{{ $siswa->nis ?? '—' }}</strong>
                    @if ($siswa->nisn)
                        &middot; NISN <strong>{{ $siswa->nisn }}</strong>
                    @endif
                    &middot;
                    @if ($siswa->is_active)
                        <x-ui.badge tone="ok">Aktif</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                    @endif
                </p>
            </div>
            <div class="card__actions">
                <x-ui.button href="{{ route('admin.siswa.edit', $siswa) }}" variant="secondary">Ubah</x-ui.button>
            </div>
        </div>

        <dl class="detail-grid">
            <div>
                <dt class="detail-grid__label">Kelas</dt>
                <dd class="detail-grid__value">
                    @if ($siswa->kelas)
                        {{ $siswa->kelas->nama }}
                    @else
                        Belum ada kelas
                    @endif
                </dd>
            </div>
            <div>
                <dt class="detail-grid__label">Tahun ajaran</dt>
                <dd class="detail-grid__value">{{ $siswa->tahunAjaran->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Jenis kelamin</dt>
                <dd class="detail-grid__value">{{ $siswa->jenis_kelamin ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Tempat, tanggal lahir</dt>
                <dd class="detail-grid__value">
                    {{ $siswa->tempat_lahir ?? '—' }}@if ($siswa->tanggal_lahir), {{ $siswa->tanggal_lahir->format('d/m/Y') }}@endif
                </dd>
            </div>
            <div>
                <dt class="detail-grid__label">Email</dt>
                <dd class="detail-grid__value">{{ $siswa->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Telepon</dt>
                <dd class="detail-grid__value">{{ $siswa->telepon ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Alamat</dt>
                <dd class="detail-grid__value">{{ $siswa->alamat ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Nama wali</dt>
                <dd class="detail-grid__value">{{ $siswa->nama_wali ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Telepon wali</dt>
                <dd class="detail-grid__value">{{ $siswa->telepon_wali ?? '—' }}</dd>
            </div>
        </dl>
    </div>
@endsection
