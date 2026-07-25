@extends('layouts.admin')

@section('title', 'Kata sandi admin lembaga')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}">Dashboard</a> /
    <a href="{{ route('admin.lembaga.index') }}">Lembaga</a> /
    <a href="{{ route('admin.lembaga.show', $lembaga) }}">{{ $lembaga->nama }}</a> / Kata sandi
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title font-display">Kata sandi admin lembaga</h1>
            <p class="page-header__description">
                Kata sandi untuk <strong>{{ $admin->name }}</strong> ({{ $admin->email }}).
            </p>
        </div>
    </div>

    <div class="form-card">
        <div class="callout-warning">
            <p><strong>Simpan kata sandi ini sekarang.</strong></p>
            <p>Kata sandi hanya ditampilkan sekali dan tidak dapat dilihat kembali setelah halaman ini ditutup atau dimuat ulang. Berikan kata sandi ini kepada admin lembaga melalui jalur yang aman.</p>
        </div>

        <div class="field">
            <label for="admin-email" class="field-label">Email</label>
            <input id="admin-email" type="text" class="field-control" value="{{ $admin->email }}" readonly>
        </div>

        <div class="password-display">
            <div class="field">
                <label for="admin-password" class="field-label">Kata sandi</label>
                <input id="admin-password" type="text" class="field-control" value="{{ $plainPassword }}" readonly data-select-on-click>
            </div>
            <x-ui.button type="button" variant="secondary" data-copy-target="admin-password">Salin</x-ui.button>
        </div>

        <div class="form-actions">
            <x-ui.button href="{{ route('admin.lembaga.show', $lembaga) }}">Kembali ke detail lembaga</x-ui.button>
        </div>
    </div>
@endsection
