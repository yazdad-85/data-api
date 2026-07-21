@extends('layouts.admin')

@section('title', 'Ubah tahun ajaran')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.tahun-ajaran.index') }}">Tahun ajaran</a> / {{ $tahunAjaran->nama }}
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Ubah tahun ajaran</h1>
            <p class="page-header__description">
                Perbarui tanggal untuk <strong>{{ $tahunAjaran->nama }}</strong>. Nama tahun ajaran dibentuk otomatis dan tidak dapat diubah.
            </p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.tahun-ajaran.update', $tahunAjaran) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <x-ui.input
                    name="nama"
                    label="Nama"
                    :value="$tahunAjaran->nama"
                    readonly
                    hint="Nama dibentuk otomatis dari tahun mulai dan tidak dapat diubah."
                />
                <x-ui.input
                    name="tanggal_mulai"
                    type="date"
                    label="Tanggal mulai"
                    required
                    :value="old('tanggal_mulai', $tahunAjaran->tanggal_mulai->format('Y-m-d'))"
                    :error="$errors->first('tanggal_mulai')"
                />
                <x-ui.input
                    name="tanggal_selesai"
                    type="date"
                    label="Tanggal selesai"
                    required
                    :value="old('tanggal_selesai', $tahunAjaran->tanggal_selesai->format('Y-m-d'))"
                    :error="$errors->first('tanggal_selesai')"
                    hint="Harus setelah tanggal mulai."
                />
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
                <x-ui.button href="{{ route('admin.tahun-ajaran.index') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
