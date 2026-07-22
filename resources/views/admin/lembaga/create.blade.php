@extends('layouts.admin')

@section('title', 'Tambah lembaga')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.lembaga.index') }}">Lembaga</a> / Tambah
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Tambah lembaga</h1>
            <p class="page-header__description">Lengkapi data lembaga baru. Kode lembaga digenerate otomatis; status aktif diatur saat dibuat.</p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.lembaga.store') }}">
            @csrf

            <div class="form-grid">
                <x-ui.input
                    name="niy_kode"
                    label="Kode NIY (2 digit)"
                    required
                    :value="old('niy_kode')"
                    :error="$errors->first('niy_kode')"
                    hint="Digunakan untuk generate NIY guru, mis. 01, 02."
                />
                <x-ui.input
                    name="nama"
                    label="Nama lembaga"
                    required
                    :value="old('nama')"
                    :error="$errors->first('nama')"
                />
                <x-ui.input
                    name="jenis"
                    label="Jenis"
                    :value="old('jenis')"
                    :error="$errors->first('jenis')"
                    hint="Contoh: sekolah, madrasah, kursus."
                />
                <x-ui.input
                    name="telepon"
                    label="Telepon"
                    :value="old('telepon')"
                    :error="$errors->first('telepon')"
                />
                <x-ui.input
                    name="email"
                    type="email"
                    label="Email"
                    :value="old('email')"
                    :error="$errors->first('email')"
                />
                <x-ui.input
                    name="kota"
                    label="Kota"
                    :value="old('kota')"
                    :error="$errors->first('kota')"
                />
                <x-ui.input
                    name="provinsi"
                    label="Provinsi"
                    :value="old('provinsi')"
                    :error="$errors->first('provinsi')"
                />
            </div>

            <x-ui.input
                name="alamat"
                label="Alamat"
                :value="old('alamat')"
                :error="$errors->first('alamat')"
            />

            <div class="form-actions">
                <x-ui.button type="submit">Simpan lembaga</x-ui.button>
                <x-ui.button href="{{ route('admin.lembaga.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
