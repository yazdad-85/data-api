@extends('layouts.admin')

@section('title', 'Profil Lembaga')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Profil Lembaga
@endsection

@section('content')
    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="callout-warning">
            <p><strong>Periksa kembali data yang dikirim:</strong></p>
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Profil Lembaga</h1>
            <p class="page-header__description">
                Lengkapi data lembaga, termasuk nama kepala dan kop surat. Kop surat akan digunakan
                sebagai kepala dokumen pada surat yang dibuat otomatis oleh sistem.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.lembaga-profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <span class="field-label">Kode lembaga</span>
                    <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $lembaga->kode }}</p>
                </div>
                <div class="field">
                    <span class="field-label">Nama lembaga</span>
                    <p class="field-control" style="background: var(--color-surface-muted, #f3f4f6);">{{ $lembaga->nama }}</p>
                    <p class="field-hint">Kode dan nama resmi hanya dapat diubah oleh Super Admin.</p>
                </div>
                <x-ui.input
                    name="nama_kepala"
                    label="Nama kepala"
                    :value="old('nama_kepala', $lembaga->nama_kepala)"
                    :error="$errors->first('nama_kepala')"
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

            <div class="form-grid">
                <x-ui.input
                    name="kop_surat"
                    type="file"
                    label="Kop surat"
                    accept=".png,image/png"
                    :error="$errors->first('kop_surat')"
                    hint="Format PNG. Maksimal 2 MB."
                />
            </div>

            @if ($kopSuratUrl)
                <div class="form-grid">
                    <div class="field">
                        <span class="field-label">Kop surat saat ini</span>
                        <p class="field-control">
                            <img src="{{ $kopSuratUrl }}" alt="Kop surat {{ $lembaga->nama }}" style="max-height: 96px; max-width: 320px;">
                        </p>
                    </div>
                </div>

                <label class="field" for="remove_kop_surat">
                    <span class="field-control">
                        <input id="remove_kop_surat" type="checkbox" name="remove_kop_surat" value="1" @checked(old('remove_kop_surat'))>
                        Hapus kop surat
                    </span>
                </label>
            @endif

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
            </div>
        </form>
    </div>
@endsection
