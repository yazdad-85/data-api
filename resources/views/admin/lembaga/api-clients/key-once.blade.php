@extends('layouts.admin')

@section('title', 'API key sekali tampil')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.lembaga.index') }}">Lembaga</a> /
    <a href="{{ route('admin.lembaga.show', $lembaga) }}">{{ $lembaga->nama }}</a> / API key
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">API key sekali tampil</h1>
            <p class="page-header__description">
                Key untuk API client <strong>{{ $apiClient->nama }}</strong> milik {{ $lembaga->nama }}.
            </p>
        </div>
    </div>

    <div class="form-card">
        <div class="callout-warning">
            <p><strong>Simpan API key ini sekarang.</strong></p>
            <p>
                Key hanya ditampilkan sekali dan tidak dapat dilihat kembali setelah halaman ini ditutup
                atau dimuat ulang. Berikan key ini kepada integrator melalui jalur yang aman. Jika key ini
                hilang, buat client baru atau minta Super Admin melakukan rotate key.
            </p>
        </div>

        <div class="field">
            <label for="api-client-prefix" class="field-label">Prefix</label>
            <input
                id="api-client-prefix"
                type="text"
                class="field-control"
                value="{{ $apiClient->api_key_prefix }}"
                readonly
            >
        </div>

        <div class="password-display">
            <div class="field">
                <label for="api-client-key" class="field-label">API key</label>
                <input
                    id="api-client-key"
                    type="text"
                    class="field-control"
                    value="{{ $plainKey }}"
                    readonly
                    onclick="this.select()"
                >
            </div>
            <x-ui.button type="button" variant="secondary" data-copy-target="api-client-key">Salin</x-ui.button>
        </div>

        <div class="form-actions">
            <x-ui.button href="{{ route('admin.lembaga.show', $lembaga) }}">Kembali ke detail lembaga</x-ui.button>
        </div>
    </div>
@endsection
