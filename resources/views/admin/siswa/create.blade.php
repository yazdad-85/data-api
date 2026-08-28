@extends('layouts.admin')

@section('title', 'Tambah siswa')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.siswa.index') }}">Siswa</a> / Tambah
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Tambah siswa</h1>
            <p class="page-header__description">
                NIS wajib diisi. Pilih mutasi masuk untuk siswa pindahan; jika kelas dipilih, siswa langsung berstatus
                <strong>aktif</strong> dengan riwayat penempatan yang sesuai.
            </p>
        </div>
    </div>

    <div class="form-card form-card--wide">
        <form method="POST" action="{{ route('admin.siswa.store') }}">
            @csrf

            <div class="form-grid">
                <x-ui.input
                    name="nis"
                    label="NIS"
                    required
                    :value="old('nis')"
                    :error="$errors->first('nis')"
                />
                <x-ui.input
                    name="nisn"
                    label="NISN"
                    :value="old('nisn')"
                    :error="$errors->first('nisn')"
                    hint="Opsional."
                />
                <x-ui.input
                    name="nama"
                    label="Nama"
                    required
                    :value="old('nama')"
                    :error="$errors->first('nama')"
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
                <x-ui.select
                    name="status_keluarga"
                    label="Status keluarga"
                    :error="$errors->first('status_keluarga')"
                >
                    <option value="" @selected(old('status_keluarga') === null || old('status_keluarga') === '')>— Pilih —</option>
                    <option value="Yatim" @selected(old('status_keluarga') === 'Yatim')>Yatim</option>
                    <option value="Piatu" @selected(old('status_keluarga') === 'Piatu')>Piatu</option>
                    <option value="Yatim Piatu" @selected(old('status_keluarga') === 'Yatim Piatu')>Yatim Piatu</option>
                    <option value="Anak Guru, Staff, dan Karyawan" @selected(old('status_keluarga') === 'Anak Guru, Staff, dan Karyawan')>Anak Guru, Staff, dan Karyawan</option>
                </x-ui.select>
                <x-ui.input
                    name="nama_ayah"
                    label="Nama ayah"
                    :value="old('nama_ayah')"
                    :error="$errors->first('nama_ayah')"
                />
                <x-ui.input
                    name="pekerjaan_ayah"
                    label="Pekerjaan ayah"
                    :value="old('pekerjaan_ayah')"
                    :error="$errors->first('pekerjaan_ayah')"
                />
                <x-ui.input
                    name="nama_ibu"
                    label="Nama ibu"
                    :value="old('nama_ibu')"
                    :error="$errors->first('nama_ibu')"
                />
                <x-ui.input
                    name="pekerjaan_ibu"
                    label="Pekerjaan ibu"
                    :value="old('pekerjaan_ibu')"
                    :error="$errors->first('pekerjaan_ibu')"
                />
                <x-ui.select
                    name="kelas_id"
                    label="Kelas"
                    :error="$errors->first('kelas_id')"
                >
                    <option value="" @selected(old('kelas_id') === null || old('kelas_id') === '')>— Belum ada kelas —</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" @selected(old('kelas_id') === $kelas->id)>
                            {{ $kelas->nama }}@if ($kelas->tahunAjaran) ({{ $kelas->tahunAjaran->nama }})@endif
                        </option>
                    @endforeach
                </x-ui.select>
                <x-ui.select
                    name="tahun_ajaran_id"
                    label="Tahun ajaran"
                    :error="$errors->first('tahun_ajaran_id')"
                    hint="Wajib jika siswa ditempatkan di kelas."
                >
                    <option value="" @selected(old('tahun_ajaran_id') === null || old('tahun_ajaran_id') === '')>— Pilih —</option>
                    @foreach ($tahunAjarans as $tahunAjaran)
                        <option value="{{ $tahunAjaran->id }}" @selected(old('tahun_ajaran_id') === $tahunAjaran->id)>
                            {{ $tahunAjaran->nama }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-ui.input
                    name="nama_wali"
                    label="Nama wali"
                    :value="old('nama_wali')"
                    :error="$errors->first('nama_wali')"
                />
                <x-ui.input
                    name="telepon_wali"
                    label="Telepon wali"
                    :value="old('telepon_wali')"
                    :error="$errors->first('telepon_wali')"
                />
                <x-ui.select
                    name="jenis_masuk"
                    label="Jenis masuk"
                    :error="$errors->first('jenis_masuk')"
                    data-mutasi-source
                >
                    <option value="siswa_baru" @selected(old('jenis_masuk', request('jenis_masuk', 'siswa_baru')) === 'siswa_baru')>Siswa baru</option>
                    <option value="mutasi_masuk" @selected(old('jenis_masuk', request('jenis_masuk')) === 'mutasi_masuk')>Mutasi masuk</option>
                </x-ui.select>
                <x-ui.input
                    name="asal_lembaga"
                    label="Asal lembaga"
                    :value="old('asal_lembaga')"
                    :error="$errors->first('asal_lembaga')"
                    hint="Wajib untuk mutasi masuk."
                    data-mutasi-field
                />
                <x-ui.input
                    name="diterima_tanggal"
                    type="date"
                    label="Diterima tanggal"
                    :value="old('diterima_tanggal')"
                    :error="$errors->first('diterima_tanggal')"
                    hint="Wajib untuk mutasi masuk."
                    data-mutasi-field
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
                <x-ui.button type="submit">Simpan siswa</x-ui.button>
                <x-ui.button href="{{ route('admin.siswa.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
