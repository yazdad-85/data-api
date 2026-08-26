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

    <div class="form-card form-card--wide">
        <form method="POST" action="{{ route('admin.guru.store') }}" enctype="multipart/form-data">
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
                    name="nik"
                    label="NIK"
                    :value="old('nik')"
                    :error="$errors->first('nik')"
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
                <x-ui.select
                    name="pendidikan_terakhir"
                    label="Pendidikan terakhir"
                    :error="$errors->first('pendidikan_terakhir')"
                >
                    <option value="" @selected(old('pendidikan_terakhir') === null || old('pendidikan_terakhir') === '')>— Pilih —</option>
                    @foreach (['SMP', 'SMA', 'S1', 'S2', 'S3'] as $pendidikan)
                        <option value="{{ $pendidikan }}" @selected(old('pendidikan_terakhir') === $pendidikan)>{{ $pendidikan }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input
                    name="instansi_pendidikan"
                    label="Instansi pendidikan"
                    :value="old('instansi_pendidikan')"
                    :error="$errors->first('instansi_pendidikan')"
                />
                <x-ui.input
                    name="jurusan"
                    label="Jurusan"
                    :value="old('jurusan')"
                    :error="$errors->first('jurusan')"
                />
                <x-ui.select
                    name="status_sertifikasi"
                    label="Status sertifikasi"
                    :error="$errors->first('status_sertifikasi')"
                >
                    <option value="" @selected(old('status_sertifikasi') === null || old('status_sertifikasi') === '')>— Pilih —</option>
                    <option value="Sudah" @selected(old('status_sertifikasi') === 'Sudah')>Sudah</option>
                    <option value="Belum" @selected(old('status_sertifikasi') === 'Belum')>Belum</option>
                </x-ui.select>
                <x-ui.select
                    name="status_inpasing"
                    label="Status inpasing"
                    :error="$errors->first('status_inpasing')"
                >
                    <option value="" @selected(old('status_inpasing') === null || old('status_inpasing') === '')>— Pilih —</option>
                    <option value="Sudah" @selected(old('status_inpasing') === 'Sudah')>Sudah</option>
                    <option value="Belum" @selected(old('status_inpasing') === 'Belum')>Belum</option>
                </x-ui.select>
                <x-ui.input
                    name="mapel_sertifikasi"
                    label="Mapel sertifikasi"
                    :value="old('mapel_sertifikasi')"
                    :error="$errors->first('mapel_sertifikasi')"
                />
                <x-ui.select
                    name="status_menikah"
                    label="Status menikah"
                    :error="$errors->first('status_menikah')"
                >
                    <option value="" @selected(old('status_menikah') === null || old('status_menikah') === '')>— Pilih —</option>
                    <option value="Sudah Menikah" @selected(old('status_menikah') === 'Sudah Menikah')>Sudah Menikah</option>
                    <option value="Belum Menikah" @selected(old('status_menikah') === 'Belum Menikah')>Belum Menikah</option>
                </x-ui.select>
                <div class="field">
                    <label for="foto" class="field-label">Foto</label>
                    <input id="foto" type="file" name="foto" accept="image/*" class="field-control" @if ($errors->has('foto')) aria-invalid="true" @endif>
                    <p class="field-hint">Format gambar, maksimal 2MB.</p>
                    @error('foto')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <x-ui.input
                    name="peg_id"
                    label="Peg-ID"
                    :value="old('peg_id')"
                    :error="$errors->first('peg_id')"
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
                <textarea id="alamat" name="alamat" class="field-control" rows="2">{{ old('alamat') }}</textarea>
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
