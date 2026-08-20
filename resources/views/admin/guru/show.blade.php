@extends('layouts.admin')

@section('title', $guru->nama)

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.guru.index') }}">Guru</a> / {{ $guru->nama }}
@endsection

@section('content')
    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    <div class="card">
        <div class="card__header">
            <div>
                <h1 class="card__title font-display">{{ $guru->nama }}</h1>
                <p class="card__meta">
                    NIY <strong>{{ $guru->niy ?? '—' }}</strong>
                    &middot;
                    @if ($guru->is_active)
                        <x-ui.badge tone="ok">Aktif</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">Nonaktif</x-ui.badge>
                    @endif
                </p>
            </div>
            <div class="card__actions">
                <x-ui.button href="{{ route('admin.guru.edit', $guru) }}" variant="secondary">Ubah</x-ui.button>
            </div>
        </div>

        <dl class="detail-grid">
            <div>
                <dt class="detail-grid__label">Tahun masuk</dt>
                <dd class="detail-grid__value">{{ $guru->tahun_masuk ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">NIK</dt>
                <dd class="detail-grid__value">{{ $guru->nik ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Peg-ID</dt>
                <dd class="detail-grid__value">{{ $guru->peg_id ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Pendidikan terakhir</dt>
                <dd class="detail-grid__value">{{ $guru->pendidikan_terakhir ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Instansi pendidikan</dt>
                <dd class="detail-grid__value">{{ $guru->instansi_pendidikan ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Jurusan</dt>
                <dd class="detail-grid__value">{{ $guru->jurusan ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Status sertifikasi</dt>
                <dd class="detail-grid__value">{{ $guru->status_sertifikasi ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Status inpasing</dt>
                <dd class="detail-grid__value">{{ $guru->status_inpasing ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Mapel sertifikasi</dt>
                <dd class="detail-grid__value">{{ $guru->mapel_sertifikasi ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Status menikah</dt>
                <dd class="detail-grid__value">{{ $guru->status_menikah ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Jenis kelamin</dt>
                <dd class="detail-grid__value">{{ $guru->jenis_kelamin ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Tempat, tanggal lahir</dt>
                <dd class="detail-grid__value">
                    {{ $guru->tempat_lahir ?? '—' }}@if ($guru->tanggal_lahir), {{ $guru->tanggal_lahir->format('d/m/Y') }}@endif
                </dd>
            </div>
            <div>
                <dt class="detail-grid__label">Status kepegawaian</dt>
                <dd class="detail-grid__value">{{ $guru->status_kepegawaian ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Email</dt>
                <dd class="detail-grid__value">{{ $guru->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Telepon</dt>
                <dd class="detail-grid__value">{{ $guru->telepon ?? '—' }}</dd>
            </div>
            <div>
                <dt class="detail-grid__label">Alamat</dt>
                <dd class="detail-grid__value">{{ $guru->alamat ?? '—' }}</dd>
            </div>
        </dl>
    </div>
@endsection
