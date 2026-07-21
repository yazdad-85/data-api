@extends('layouts.admin')

@section('title', $karyawan->nama)

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.karyawan.index') }}">Karyawan</a> / {{ $karyawan->nama }}
@endsection

@section('content')
    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    <div class="card">
        <div class="card__header">
            <div>
                <h1 class="card__title font-display">{{ $karyawan->nama }}</h1>
                <p class="card__meta">
                    NIK <strong>{{ $karyawan->nik_pegawai ?? '—' }}</strong>
                    &middot;
                    @if ($karyawan->is_active)
                        <x-ui.badge tone="ok">Aktif</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                    @endif
                </p>
            </div>
            <div class="card__actions">
                <x-ui.button href="{{ route('admin.karyawan.edit', $karyawan) }}" variant="secondary">Ubah</x-ui.button>
            </div>
        </div>

        <dl class="detail-grid">
            <div>
                <dt class="detail-grid__label">Jabatan</dt>
                <dd class="detail-grid__value">{{ $karyawan->jabatan ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Jenis kelamin</dt>
                <dd class="detail-grid__value">{{ $karyawan->jenis_kelamin ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Email</dt>
                <dd class="detail-grid__value">{{ $karyawan->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Telepon</dt>
                <dd class="detail-grid__value">{{ $karyawan->telepon ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Alamat</dt>
                <dd class="detail-grid__value">{{ $karyawan->alamat ?? '—' }}</dd>
            </div>
        </dl>
    </div>
@endsection
