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
                Perbarui data untuk <strong>{{ $siswa->nama }}</strong>.
            </p>
        </div>
    </div>

    <div class="form-card">
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
                    name="kelas_id"
                    label="Kelas"
                    :error="$errors->first('kelas_id')"
                >
                    <option value="" @selected(old('kelas_id', $siswa->kelas_id) === null || old('kelas_id', $siswa->kelas_id) === '')>— Belum ada kelas —</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" @selected(old('kelas_id', $siswa->kelas_id) === $kelas->id)>
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
                    <option value="" @selected(old('tahun_ajaran_id', $siswa->tahun_ajaran_id) === null || old('tahun_ajaran_id', $siswa->tahun_ajaran_id) === '')>— Pilih —</option>
                    @foreach ($tahunAjarans as $tahunAjaran)
                        <option value="{{ $tahunAjaran->id }}" @selected(old('tahun_ajaran_id', $siswa->tahun_ajaran_id) === $tahunAjaran->id)>
                            {{ $tahunAjaran->nama }}
                        </option>
                    @endforeach
                </x-ui.select>
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
                <textarea id="alamat" name="alamat" class="field-control" rows="3">{{ old('alamat', $siswa->alamat) }}</textarea>
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
