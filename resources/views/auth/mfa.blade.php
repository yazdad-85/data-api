@extends('layouts.guest')

@section('title', 'Verifikasi MFA — Pusat Data')

@section('content')
    <h1>Verifikasi MFA</h1>
    <p>Masukkan kode autentikasi dari aplikasi autentikator atau kode pemulihan.</p>

    @if ($errors->any())
        <p class="guest-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login.mfa') }}">
        @csrf
        <label for="code">Kode autentikasi</label>
        <input id="code" type="text" name="code" inputmode="text" autocomplete="one-time-code" required autofocus>

        <button type="submit">Verifikasi</button>
    </form>
@endsection
