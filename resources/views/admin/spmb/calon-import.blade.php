@extends('layouts.admin')

@section('title', 'Import Calon Murid')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.spmb-distribusi.create') }}">SPMB</a> / Import calon murid
@endsection

@section('content')
    @if (session('status'))
        <p class="flash-status">{{ session('status') }}</p>
    @endif

    @if (session('import_errors'))
        <div class="callout-warning">
            <p><strong>Detail baris gagal:</strong></p>
            <ul>
                @foreach (session('import_errors') as $error)
                    <li>Baris {{ $error['row'] }}: {{ $error['message'] }}</li>
                @endforeach
            </ul>
        </div>
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
            <h1 class="page-header__title font-display">Import calon murid</h1>
            <p class="page-header__description">
                Upload data siswa hasil SPMB sebagai calon murid — belum ditempatkan ke kelas mana pun.
                NIS boleh dikosongkan karena calon murid belum resmi diterima. Setelah diimport, tempatkan
                mereka ke kelas lewat menu <a href="{{ route('admin.spmb-distribusi.create') }}">Distribusi SPMB</a>.
            </p>
        </div>
        <div class="page-header__actions">
            <x-ui.button href="{{ route('admin.spmb-calon.template') }}" variant="secondary">Unduh template calon murid</x-ui.button>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.spmb-calon.store') }}" enctype="multipart/form-data">
            @csrf

            <x-ui.select
                name="tahun_ajaran_id"
                label="Tahun ajaran (opsional)"
                :error="$errors->first('tahun_ajaran_id')"
                hint="Dipakai untuk memudahkan filter di Distribusi SPMB nanti."
            >
                <option value="" @selected(old('tahun_ajaran_id') === null || old('tahun_ajaran_id') === '')>— Tidak ditentukan —</option>
                @foreach ($tahunAjarans as $tahunAjaran)
                    <option value="{{ $tahunAjaran->id }}" @selected(old('tahun_ajaran_id') === $tahunAjaran->id)>
                        {{ $tahunAjaran->nama }}
                    </option>
                @endforeach
            </x-ui.select>

            <div class="field">
                <label for="calon-import-file" class="field-label">File Excel</label>
                <input id="calon-import-file" type="file" name="file" accept=".xlsx,.xls" class="field-control" required>
                @error('file')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Import</x-ui.button>
                <x-ui.button href="{{ route('admin.spmb-distribusi.create') }}" variant="secondary">Batal</x-ui.button>
            </div>
        </form>
    </div>
@endsection
