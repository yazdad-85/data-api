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
                NIS wajib diisi. Jika kelas dipilih, siswa langsung berstatus <strong>aktif</strong> dengan penempatan awal;
                tanpa kelas, siswa disimpan sebagai <strong>calon</strong> dan dapat ditempatkan nanti dari halaman detail.
            </p>
        </div>
    </div>

    <div class="form-card">
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
            </div>

            <div class="field">
                <label for="alamat" class="field-label">Alamat</label>
                <textarea id="alamat" name="alamat" class="field-control" rows="3">{{ old('alamat') }}</textarea>
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
