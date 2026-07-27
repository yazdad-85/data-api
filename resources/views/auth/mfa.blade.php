@extends('layouts.guest')

@php
    $branding = app_branding();
@endphp

@section('title', 'Verifikasi MFA — '.$branding['name'])

@section('hero')
    @include('partials.auth-hero', [
        'heroTitle' => 'Verifikasi akses sebelum masuk ke dashboard.',
        'heroBody' => 'Langkah tambahan ini menjaga akun Super Admin tetap aman sebelum melanjutkan ke panel administrasi.',
    ])
@endsection

@section('content')
    <div class="auth-panel__header">
        <h2 class="auth-panel__title font-display">Verifikasi MFA</h2>
        <p class="auth-panel__subtitle">Masukkan kode autentikasi dari aplikasi autentikator atau kode pemulihan.</p>
    </div>

    @if ($errors->any())
        <p class="guest-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login.mfa') }}" class="auth-form">
        @csrf

        <label for="code">Kode autentikasi</label>
        <input id="code" type="text" name="code" inputmode="text" autocomplete="one-time-code" required autofocus>

        <button type="submit">Verifikasi</button>
    </form>
@endsection
