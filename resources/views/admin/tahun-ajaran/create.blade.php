@extends('layouts.admin')

@section('title', 'Tambah tahun ajaran')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.tahun-ajaran.index') }}">Tahun ajaran</a> / Tambah
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Tambah tahun ajaran</h1>
            <p class="page-header__description">
                Nama tahun ajaran dibuat otomatis dari tahun mulai, misalnya <strong>2026/2027</strong>.
                Tahun ajaran baru selalu dibuat dalam status nonaktif.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.tahun-ajaran.store') }}">
            @csrf

            <div class="form-grid">
                <x-ui.input
                    name="tahun_mulai"
                    type="number"
                    label="Tahun mulai"
                    required
                    :value="old('tahun_mulai')"
                    :error="$errors->first('tahun_mulai')"
                    hint="Contoh: isi 2026 untuk tahun ajaran 2026/2027."
                />
                <x-ui.input
                    name="tanggal_mulai"
                    type="date"
                    label="Tanggal mulai"
                    required
                    :value="old('tanggal_mulai')"
                    :error="$errors->first('tanggal_mulai')"
                />
                <x-ui.input
                    name="tanggal_selesai"
                    type="date"
                    label="Tanggal selesai"
                    required
                    :value="old('tanggal_selesai')"
                    :error="$errors->first('tanggal_selesai')"
                    hint="Harus setelah tanggal mulai."
                />
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan tahun ajaran</x-ui.button>
                <x-ui.button href="{{ route('admin.tahun-ajaran.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
