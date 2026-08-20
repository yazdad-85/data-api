@extends('layouts.admin')

@section('title', 'Ubah guru')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.guru.index') }}">Guru</a> / {{ $guru->nama }}
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Ubah guru</h1>
            <p class="page-header__description">
                Perbarui data untuk <strong>{{ $guru->nama }}</strong>.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.guru.update', $guru) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <x-ui.input
                    name="nama"
                    label="Nama"
                    required
                    :value="old('nama', $guru->nama)"
                    :error="$errors->first('nama')"
                />
                <x-ui.input
                    name="nik"
                    label="NIK"
                    :value="old('nik', $guru->nik)"
                    :error="$errors->first('nik')"
                />
                <div class="field">
                    <span class="field-label">NIY</span>
                    <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $guru->niy ?? '—' }}</p>
                    <p class="field-hint">NIY tidak dapat diubah setelah dibuat.</p>
                </div>
                <div class="field">
                    <span class="field-label">Tahun masuk</span>
                    <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $guru->tahun_masuk ?? '—' }}</p>
                </div>
                <x-ui.input
                    name="nuptk"
                    label="NUPTK"
                    :value="old('nuptk', $guru->nuptk)"
                    :error="$errors->first('nuptk')"
                />
                <x-ui.select
                    name="jenis_kelamin"
                    label="Jenis kelamin"
                    :error="$errors->first('jenis_kelamin')"
                >
                    <option value="" @selected(old('jenis_kelamin', $guru->jenis_kelamin) === null)>— Pilih —</option>
                    <option value="L" @selected(old('jenis_kelamin', $guru->jenis_kelamin) === 'L')>Laki-laki</option>
                    <option value="P" @selected(old('jenis_kelamin', $guru->jenis_kelamin) === 'P')>Perempuan</option>
                </x-ui.select>
                <x-ui.select
                    name="pendidikan_terakhir"
                    label="Pendidikan terakhir"
                    :error="$errors->first('pendidikan_terakhir')"
                >
                    <option value="" @selected(old('pendidikan_terakhir', $guru->pendidikan_terakhir) === null || old('pendidikan_terakhir', $guru->pendidikan_terakhir) === '')>— Pilih —</option>
                    @foreach (['SMP', 'SMA', 'S1', 'S2', 'S3'] as $pendidikan)
                        <option value="{{ $pendidikan }}" @selected(old('pendidikan_terakhir', $guru->pendidikan_terakhir) === $pendidikan)>{{ $pendidikan }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input
                    name="instansi_pendidikan"
                    label="Instansi pendidikan"
                    :value="old('instansi_pendidikan', $guru->instansi_pendidikan)"
                    :error="$errors->first('instansi_pendidikan')"
                />
                <x-ui.input
                    name="jurusan"
                    label="Jurusan"
                    :value="old('jurusan', $guru->jurusan)"
                    :error="$errors->first('jurusan')"
                />
                <x-ui.select
                    name="status_sertifikasi"
                    label="Status sertifikasi"
                    :error="$errors->first('status_sertifikasi')"
                >
                    <option value="" @selected(old('status_sertifikasi', $guru->status_sertifikasi) === null || old('status_sertifikasi', $guru->status_sertifikasi) === '')>— Pilih —</option>
                    <option value="Sudah" @selected(old('status_sertifikasi', $guru->status_sertifikasi) === 'Sudah')>Sudah</option>
                    <option value="Belum" @selected(old('status_sertifikasi', $guru->status_sertifikasi) === 'Belum')>Belum</option>
                </x-ui.select>
                <x-ui.select
                    name="status_inpasing"
                    label="Status inpasing"
                    :error="$errors->first('status_inpasing')"
                >
                    <option value="" @selected(old('status_inpasing', $guru->status_inpasing) === null || old('status_inpasing', $guru->status_inpasing) === '')>— Pilih —</option>
                    <option value="Sudah" @selected(old('status_inpasing', $guru->status_inpasing) === 'Sudah')>Sudah</option>
                    <option value="Belum" @selected(old('status_inpasing', $guru->status_inpasing) === 'Belum')>Belum</option>
                </x-ui.select>
                <x-ui.input
                    name="mapel_sertifikasi"
                    label="Mapel sertifikasi"
                    :value="old('mapel_sertifikasi', $guru->mapel_sertifikasi)"
                    :error="$errors->first('mapel_sertifikasi')"
                />
                <x-ui.select
                    name="status_menikah"
                    label="Status menikah"
                    :error="$errors->first('status_menikah')"
                >
                    <option value="" @selected(old('status_menikah', $guru->status_menikah) === null || old('status_menikah', $guru->status_menikah) === '')>— Pilih —</option>
                    <option value="Sudah Menikah" @selected(old('status_menikah', $guru->status_menikah) === 'Sudah Menikah')>Sudah Menikah</option>
                    <option value="Belum Menikah" @selected(old('status_menikah', $guru->status_menikah) === 'Belum Menikah')>Belum Menikah</option>
                </x-ui.select>
                <x-ui.input
                    name="tempat_lahir"
                    label="Tempat lahir"
                    :value="old('tempat_lahir', $guru->tempat_lahir)"
                    :error="$errors->first('tempat_lahir')"
                />
                <x-ui.input
                    name="tanggal_lahir"
                    type="date"
                    label="Tanggal lahir"
                    :value="old('tanggal_lahir', optional($guru->tanggal_lahir)->format('Y-m-d'))"
                    :error="$errors->first('tanggal_lahir')"
                />
                <x-ui.input
                    name="email"
                    type="email"
                    label="Email"
                    :value="old('email', $guru->email)"
                    :error="$errors->first('email')"
                />
                <x-ui.input
                    name="telepon"
                    label="Telepon"
                    :value="old('telepon', $guru->telepon)"
                    :error="$errors->first('telepon')"
                />
                <x-ui.input
                    name="status_kepegawaian"
                    label="Status kepegawaian"
                    :value="old('status_kepegawaian', $guru->status_kepegawaian)"
                    :error="$errors->first('status_kepegawaian')"
                    hint="Misalnya: PNS, GTY, honorer."
                />
            </div>

            <div class="field">
                <label for="alamat" class="field-label">Alamat</label>
                <textarea id="alamat" name="alamat" class="field-control" rows="3">{{ old('alamat', $guru->alamat) }}</textarea>
                @error('alamat')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                <x-ui.button href="{{ route('admin.guru.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
