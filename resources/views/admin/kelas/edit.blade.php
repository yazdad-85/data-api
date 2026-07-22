@extends('layouts.admin')

@section('title', 'Ubah kelas')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.kelas.index') }}">Kelas</a> /
    <a href="{{ route('admin.kelas.show', $kelas) }}">{{ $kelas->nama }}</a> / Ubah
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Ubah kelas</h1>
            <p class="page-header__description">
                Perbarui data kelas <strong>{{ $kelas->nama }}</strong>.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.kelas.update', $kelas) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <x-ui.select
                    name="tahun_ajaran_id"
                    label="Tahun ajaran"
                    required
                    :error="$errors->first('tahun_ajaran_id')"
                >
                    <option value="" @selected(old('tahun_ajaran_id', $kelas->tahun_ajaran_id) === null)>— Pilih —</option>
                    @foreach ($tahunAjarans as $tahunAjaran)
                        <option
                            value="{{ $tahunAjaran->id }}"
                            @selected(old('tahun_ajaran_id', $kelas->tahun_ajaran_id) === $tahunAjaran->id)
                        >
                            {{ $tahunAjaran->nama }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-ui.input
                    name="nama"
                    label="Nama kelas"
                    required
                    :value="old('nama', $kelas->nama)"
                    :error="$errors->first('nama')"
                />
                <x-ui.input
                    name="tingkat"
                    label="Tingkat"
                    :value="old('tingkat', $kelas->tingkat)"
                    :error="$errors->first('tingkat')"
                />
                <x-ui.select
                    name="wali_kelas_guru_id"
                    label="Wali kelas"
                    :error="$errors->first('wali_kelas_guru_id')"
                >
                    <option value="" @selected(old('wali_kelas_guru_id', $kelas->wali_kelas_guru_id) === null || old('wali_kelas_guru_id', $kelas->wali_kelas_guru_id) === '')>— Tidak ada —</option>
                    @foreach ($gurus as $guru)
                        <option
                            value="{{ $guru->id }}"
                            @selected(old('wali_kelas_guru_id', $kelas->wali_kelas_guru_id) === $guru->id)
                        >
                            {{ $guru->nama }}@if ($guru->niy) ({{ $guru->niy }})@endif
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                <x-ui.button href="{{ route('admin.kelas.show', $kelas) }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
