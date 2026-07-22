@extends('layouts.admin')

@section('title', 'Tambah kelas')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.kelas.index') }}">Kelas</a> / Tambah
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Tambah kelas</h1>
            <p class="page-header__description">
                Buat kelas baru untuk tahun ajaran yang dipilih.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.kelas.store') }}">
            @csrf

            <div class="form-grid">
                <x-ui.select
                    name="tahun_ajaran_id"
                    label="Tahun ajaran"
                    required
                    :error="$errors->first('tahun_ajaran_id')"
                >
                    <option value="" @selected(old('tahun_ajaran_id') === null)>— Pilih —</option>
                    @foreach ($tahunAjarans as $tahunAjaran)
                        <option value="{{ $tahunAjaran->id }}" @selected(old('tahun_ajaran_id') === $tahunAjaran->id)>
                            {{ $tahunAjaran->nama }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-ui.input
                    name="nama"
                    label="Nama kelas"
                    required
                    :value="old('nama')"
                    :error="$errors->first('nama')"
                    hint="Misalnya: VII-A, X-IPA-1."
                />
                <x-ui.input
                    name="tingkat"
                    label="Tingkat"
                    :value="old('tingkat')"
                    :error="$errors->first('tingkat')"
                    hint="Opsional, misalnya: 7, 10, XII."
                />
                <x-ui.select
                    name="wali_kelas_guru_id"
                    label="Wali kelas"
                    :error="$errors->first('wali_kelas_guru_id')"
                >
                    <option value="" @selected(old('wali_kelas_guru_id') === null || old('wali_kelas_guru_id') === '')>— Tidak ada —</option>
                    @foreach ($gurus as $guru)
                        <option value="{{ $guru->id }}" @selected(old('wali_kelas_guru_id') === $guru->id)>
                            {{ $guru->nama }}@if ($guru->niy) ({{ $guru->niy }})@endif
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan kelas</x-ui.button>
                <x-ui.button href="{{ route('admin.kelas.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
