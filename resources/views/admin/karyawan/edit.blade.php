@extends('layouts.admin')

@section('title', 'Ubah karyawan')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.karyawan.index') }}">Karyawan</a> / {{ $karyawan->nama }}
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Ubah karyawan</h1>
            <p class="page-header__description">
                Perbarui data untuk <strong>{{ $karyawan->nama }}</strong>.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.karyawan.update', $karyawan) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <x-ui.input
                    name="nama"
                    label="Nama"
                    required
                    :value="old('nama', $karyawan->nama)"
                    :error="$errors->first('nama')"
                />
                <div class="field">
                    <span class="field-label">NIK pegawai</span>
                    <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $karyawan->nik_pegawai ?? '—' }}</p>
                    <p class="field-hint">NIK (format NIY) tidak dapat diubah setelah dibuat.</p>
                </div>
                <div class="field">
                    <span class="field-label">Tahun masuk</span>
                    <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $karyawan->tahun_masuk ?? '—' }}</p>
                </div>
                <x-ui.select
                    name="jenis_kelamin"
                    label="Jenis kelamin"
                    :error="$errors->first('jenis_kelamin')"
                >
                    <option value="" @selected(old('jenis_kelamin', $karyawan->jenis_kelamin) === null)>— Pilih —</option>
                    <option value="L" @selected(old('jenis_kelamin', $karyawan->jenis_kelamin) === 'L')>Laki-laki</option>
                    <option value="P" @selected(old('jenis_kelamin', $karyawan->jenis_kelamin) === 'P')>Perempuan</option>
                </x-ui.select>
                <x-ui.input
                    name="jabatan"
                    label="Jabatan"
                    :value="old('jabatan', $karyawan->jabatan)"
                    :error="$errors->first('jabatan')"
                />
                <x-ui.input
                    name="email"
                    type="email"
                    label="Email"
                    :value="old('email', $karyawan->email)"
                    :error="$errors->first('email')"
                />
                <x-ui.input
                    name="telepon"
                    label="Telepon"
                    :value="old('telepon', $karyawan->telepon)"
                    :error="$errors->first('telepon')"
                />
            </div>

            <div class="field">
                <label for="alamat" class="field-label">Alamat</label>
                <textarea id="alamat" name="alamat" class="field-control" rows="3">{{ old('alamat', $karyawan->alamat) }}</textarea>
                @error('alamat')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                <x-ui.button href="{{ route('admin.karyawan.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
