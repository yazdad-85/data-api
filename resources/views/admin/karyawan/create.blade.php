@extends('layouts.admin')

@section('title', 'Tambah karyawan')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.karyawan.index') }}">Karyawan</a> / Tambah
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Tambah karyawan</h1>
            <p class="page-header__description">
                Karyawan baru berstatus aktif secara default.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.karyawan.store') }}">
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
                    name="nik_pegawai"
                    label="NIK pegawai"
                    :value="old('nik_pegawai')"
                    :error="$errors->first('nik_pegawai')"
                />
                <x-ui.select
                    name="jenis_kelamin"
                    label="Jenis kelamin"
                    :error="$errors->first('jenis_kelamin')"
                >
                    <option value="" @selected(old('jenis_kelamin') === null)>— Pilih —</option>
                    <option value="L" @selected(old('jenis_kelamin') === 'L')>Laki-laki</option>
                    <option value="P" @selected(old('jenis_kelamin') === 'P')>Perempuan</option>
                </x-ui.select>
                <x-ui.input
                    name="jabatan"
                    label="Jabatan"
                    :value="old('jabatan')"
                    :error="$errors->first('jabatan')"
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
            </div>

            <div class="field">
                <label for="alamat" class="field-label">Alamat</label>
                <textarea id="alamat" name="alamat" class="field-control" rows="3">{{ old('alamat') }}</textarea>
                @error('alamat')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan karyawan</x-ui.button>
                <x-ui.button href="{{ route('admin.karyawan.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
