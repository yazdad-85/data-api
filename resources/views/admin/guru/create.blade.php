@extends('layouts.admin')

@section('title', 'Tambah guru')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.guru.index') }}">Guru</a> / Tambah
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Tambah guru</h1>
            <p class="page-header__description">
                Guru baru berstatus aktif secara default. NIY digenerate otomatis dari tahun masuk dan jenis kelamin.
            </p>
        </div>
    </div>

    @if (! $lembaga->niy_kode)
        <div class="callout-warning">
            <p>
                Lembaga belum memiliki <strong>kode NIY</strong>. Hubungi Super Admin untuk melengkapi data lembaga
                sebelum menambah guru.
            </p>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.guru.store') }}">
            @csrf

            <div class="form-grid">
                <x-ui.input
                    name="nama"
                    label="Nama"
                    required
                    :value="old('nama')"
                    :error="$errors->first('nama')"
                />
                <x-ui.input
                    name="tahun_masuk"
                    type="number"
                    label="Tahun masuk"
                    required
                    :value="old('tahun_masuk')"
                    :error="$errors->first('tahun_masuk')"
                    hint="Tahun penuh, mis. 1989 atau 2024."
                />
                <x-ui.select
                    name="jenis_kelamin"
                    label="Jenis kelamin"
                    required
                    :error="$errors->first('jenis_kelamin')"
                >
                    <option value="" @selected(old('jenis_kelamin') === null)>— Pilih —</option>
                    <option value="L" @selected(old('jenis_kelamin') === 'L')>Laki-laki</option>
                    <option value="P" @selected(old('jenis_kelamin') === 'P')>Perempuan</option>
                </x-ui.select>
                <x-ui.input
                    name="nuptk"
                    label="NUPTK"
                    :value="old('nuptk')"
                    :error="$errors->first('nuptk')"
                />
                <x-ui.input
                    name="tempat_lahir"
                    label="Tempat lahir"
                    :value="old('tempat_lahir')"
                    :error="$errors->first('tempat_lahir')"
                />
                <x-ui.input
                    name="tanggal_lahir"
                    type="date"
                    label="Tanggal lahir"
                    :value="old('tanggal_lahir')"
                    :error="$errors->first('tanggal_lahir')"
                />
                <x-ui.input
                    name="email"
                    type="email"
                    label="Email"
                    :value="old('email')"
                    :error="$errors->first('email')"
                />
                <x-ui.input
                    name="telepon"
                    label="Telepon"
                    :value="old('telepon')"
                    :error="$errors->first('telepon')"
                />
                <x-ui.input
                    name="status_kepegawaian"
                    label="Status kepegawaian"
                    :value="old('status_kepegawaian')"
                    :error="$errors->first('status_kepegawaian')"
                    hint="Misalnya: PNS, GTY, honorer."
                />
            </div>

            <div class="field">
                <label for="alamat" class="field-label">Alamat</label>
                <textarea id="alamat" name="alamat" class="field-control" rows="3">{{ old('alamat') }}</textarea>
                @error('alamat')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <x-ui.button type="submit" :disabled="! $lembaga->niy_kode">Simpan guru</x-ui.button>
                <x-ui.button href="{{ route('admin.guru.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
