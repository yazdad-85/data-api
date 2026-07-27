@extends('layouts.admin')

@section('title', 'Pengaturan')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> / Pengaturan
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
            <h1 class="page-header__title font-display">Pengaturan</h1>
            <p class="page-header__description">Kelola branding aplikasi dan backup database.</p>
        </div>
    </div>

    <div class="form-card">
        <h2 class="font-display">Branding</h2>
        <form method="POST" action="{{ route('admin.settings.branding') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <x-ui.input
                    name="app_name"
                    label="Nama aplikasi"
                    required
                    :value="old('app_name', $settings->app_name)"
                    :error="$errors->first('app_name')"
                />
                <x-ui.input
                    name="logo"
                    type="file"
                    label="Logo"
                    accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                    :error="$errors->first('logo')"
                    hint="PNG, JPEG, atau WebP. Maksimal 2 MB."
                />
            </div>

            @if ($branding['logo_url'] || $branding['favicon_url'])
                <div class="form-grid">
                    @if ($branding['logo_url'])
                        <div class="field">
                            <span class="field-label">Logo saat ini</span>
                            <p class="field-control">
                                <img src="{{ $branding['logo_url'] }}" alt="Logo {{ $branding['name'] }}" style="max-height: 64px; max-width: 220px;">
                            </p>
                        </div>
                    @endif
                    @if ($branding['favicon_url'])
                        <div class="field">
                            <span class="field-label">Favicon saat ini</span>
                            <p class="field-control">
                                <img src="{{ $branding['favicon_url'] }}" alt="Favicon {{ $branding['name'] }}" width="32" height="32">
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            @if ($settings->logo_path)
                <label class="field" for="remove_logo">
                    <span class="field-control">
                        <input id="remove_logo" type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))>
                        Hapus logo
                    </span>
                </label>
            @endif

            <div class="form-actions">
                <x-ui.button type="submit">Simpan perubahan</x-ui.button>
            </div>
        </form>
    </div>

    <div class="form-card">
        <h2 class="font-display">Backup database</h2>
        <p>Unduh dump PostgreSQL untuk disimpan secara aman. Restore tetap dilakukan manual oleh operator server. File backup berisi data sensitif.</p>

        <form method="POST" action="{{ route('admin.settings.backup') }}">
            @csrf

            <div class="form-grid">
                <x-ui.input
                    name="current_password"
                    type="password"
                    label="Password saat ini"
                    required
                    :value="''"
                    :error="$errors->first('current_password')"
                />
            </div>

            <div class="form-actions">
                <x-ui.button type="submit">Unduh backup</x-ui.button>
            </div>
        </form>
    </div>
@endsection
