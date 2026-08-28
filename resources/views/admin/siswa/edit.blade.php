@extends('layouts.admin')

@section('title', 'Ubah siswa')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.siswa.index') }}">Siswa</a> / {{ $siswa->nama }}
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Ubah siswa</h1>
            <p class="page-header__description">
                Perbarui data untuk <strong>{{ $siswa->nama }}</strong>. Kelas dan tahun ajaran tidak dapat diubah di sini —
                gunakan aksi <strong>Tempatkan ke kelas</strong> atau <strong>Pindah kelas</strong> pada halaman detail siswa
                agar riwayat penempatan tetap konsisten.
            </p>
        </div>
    </div>

    <div class="form-card form-card--wide">
        <form method="POST" action="{{ route('admin.siswa.update', $siswa) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <x-ui.input
                    name="nis"
                    label="NIS"
                    required
                    :value="old('nis', $siswa->nis)"
                    :error="$errors->first('nis')"
                />
                <x-ui.input
                    name="nisn"
                    label="NISN"
                    :value="old('nisn', $siswa->nisn)"
                    :error="$errors->first('nisn')"
                    hint="Opsional."
                />
                <x-ui.input
                    name="nama"
                    label="Nama"
                    required
                    :value="old('nama', $siswa->nama)"
                    :error="$errors->first('nama')"
                />
                <x-ui.select
                    name="jenis_kelamin"
                    label="Jenis kelamin"
                    :error="$errors->first('jenis_kelamin')"
                >
                    <option value="" @selected(old('jenis_kelamin', $siswa->jenis_kelamin) === null)>— Pilih —</option>
                    <option value="L" @selected(old('jenis_kelamin', $siswa->jenis_kelamin) === 'L')>Laki-laki</option>
                    <option value="P" @selected(old('jenis_kelamin', $siswa->jenis_kelamin) === 'P')>Perempuan</option>
                </x-ui.select>
                <x-ui.input
                    name="tempat_lahir"
                    label="Tempat lahir"
                    :value="old('tempat_lahir', $siswa->tempat_lahir)"
                    :error="$errors->first('tempat_lahir')"
                />
                <x-ui.input
                    name="tanggal_lahir"
                    type="date"
                    label="Tanggal lahir"
                    :value="old('tanggal_lahir', optional($siswa->tanggal_lahir)->format('Y-m-d'))"
                    :error="$errors->first('tanggal_lahir')"
                />
                <x-ui.input
                    name="email"
                    type="email"
                    label="Email"
                    :value="old('email', $siswa->email)"
                    :error="$errors->first('email')"
                />
                <x-ui.input
                    name="telepon"
                    label="Telepon"
                    :value="old('telepon', $siswa->telepon)"
                    :error="$errors->first('telepon')"
                />
                <x-ui.select
                    name="status_keluarga"
                    label="Status keluarga"
                    :error="$errors->first('status_keluarga')"
                >
                    <option value="" @selected(old('status_keluarga', $siswa->status_keluarga) === null || old('status_keluarga', $siswa->status_keluarga) === '')>— Pilih —</option>
                    <option value="Yatim" @selected(old('status_keluarga', $siswa->status_keluarga) === 'Yatim')>Yatim</option>
                    <option value="Piatu" @selected(old('status_keluarga', $siswa->status_keluarga) === 'Piatu')>Piatu</option>
                    <option value="Yatim Piatu" @selected(old('status_keluarga', $siswa->status_keluarga) === 'Yatim Piatu')>Yatim Piatu</option>
                    <option value="Anak Guru, Staff, dan Karyawan" @selected(old('status_keluarga', $siswa->status_keluarga) === 'Anak Guru, Staff, dan Karyawan')>Anak Guru, Staff, dan Karyawan</option>
                </x-ui.select>
                <x-ui.input
                    name="nama_ayah"
                    label="Nama ayah"
                    :value="old('nama_ayah', $siswa->nama_ayah)"
                    :error="$errors->first('nama_ayah')"
                />
                <x-ui.input
                    name="pekerjaan_ayah"
                    label="Pekerjaan ayah"
                    :value="old('pekerjaan_ayah', $siswa->pekerjaan_ayah)"
                    :error="$errors->first('pekerjaan_ayah')"
                />
                <x-ui.input
                    name="nama_ibu"
                    label="Nama ibu"
                    :value="old('nama_ibu', $siswa->nama_ibu)"
                    :error="$errors->first('nama_ibu')"
                />
                <x-ui.input
                    name="pekerjaan_ibu"
                    label="Pekerjaan ibu"
                    :value="old('pekerjaan_ibu', $siswa->pekerjaan_ibu)"
                    :error="$errors->first('pekerjaan_ibu')"
                />
                <div class="field">
                    <span class="field-label">Kelas</span>
                    <p class="field-hint">
                        {{ $siswa->kelas->nama ?? 'Belum ada kelas' }} &middot;
                        <a href="{{ route('admin.siswa.show', $siswa) }}">ubah lewat aksi lifecycle</a>
                    </p>
                </div>
                <x-ui.input
                    name="nama_wali"
                    label="Nama wali"
                    :value="old('nama_wali', $siswa->nama_wali)"
                    :error="$errors->first('nama_wali')"
                />
                <x-ui.input
                    name="telepon_wali"
                    label="Telepon wali"
                    :value="old('telepon_wali', $siswa->telepon_wali)"
                    :error="$errors->first('telepon_wali')"
                />
            </div>

            <div class="field">
                <label for="alamat" class="field-label">Alamat</label>
                <textarea id="alamat" name="alamat" class="field-control" rows="2">{{ old('alamat', $siswa->alamat) }}</textarea>
                @error('alamat')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                <x-ui.button href="{{ route('admin.siswa.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
