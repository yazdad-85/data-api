@extends('layouts.admin')

@section('title', 'Ubah lembaga')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.lembaga.index') }}">Lembaga</a> /
    <a href="{{ route('admin.lembaga.show', $lembaga) }}">{{ $lembaga->nama }}</a> / Ubah
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Ubah lembaga</h1>
            <p class="page-header__description">
                Perbarui data <strong>{{ $lembaga->nama }}</strong>. Status aktif diatur dari halaman detail.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.lembaga.update', $lembaga) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <x-ui.input
                    name="kode"
                    label="Kode lembaga"
                    required
                    :value="old('kode', $lembaga->kode)"
                    :error="$errors->first('kode')"
                    hint="Maksimal 30 karakter, harus unik."
                />
                <x-ui.input
                    name="nama"
                    label="Nama lembaga"
                    required
                    :value="old('nama', $lembaga->nama)"
                    :error="$errors->first('nama')"
                />
                <x-ui.input
                    name="jenis"
                    label="Jenis"
                    :value="old('jenis', $lembaga->jenis)"
                    :error="$errors->first('jenis')"
                    hint="Contoh: sekolah, madrasah, kursus."
                />
                <x-ui.input
                    name="telepon"
                    label="Telepon"
                    :value="old('telepon', $lembaga->telepon)"
                    :error="$errors->first('telepon')"
                />
                <x-ui.input
                    name="email"
                    type="email"
                    label="Email"
                    :value="old('email', $lembaga->email)"
                    :error="$errors->first('email')"
                />
                <x-ui.input
                    name="kota"
                    label="Kota"
                    :value="old('kota', $lembaga->kota)"
                    :error="$errors->first('kota')"
                />
                <x-ui.input
                    name="provinsi"
                    label="Provinsi"
                    :value="old('provinsi', $lembaga->provinsi)"
                    :error="$errors->first('provinsi')"
                />
            </div>

            <x-ui.input
                name="alamat"
                label="Alamat"
                :value="old('alamat', $lembaga->alamat)"
                :error="$errors->first('alamat')"
            />

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                <x-ui.button href="{{ route('admin.lembaga.show', $lembaga) }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
