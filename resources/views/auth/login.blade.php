@extends('layouts.guest')

@php
    $branding = app_branding();
@endphp

@section('title', 'Masuk — '.$branding['name'])

@section('hero')
    @include('partials.auth-hero', [
        'heroTitle' => 'Kelola data lembaga dengan aman dan modern.',
        'heroBody' => 'Masuk untuk mengakses pusat data, sinkronisasi, dan administrasi lembaga dalam satu sistem terintegrasi.',
    ])
@endsection

@section('content')
    <div class="auth-panel__header">
        <h2 class="auth-panel__title font-display">Masuk</h2>
        <p class="auth-panel__subtitle">Masuk ke panel administrasi.</p>
    </div>

    @if ($errors->any())
        <p class="guest-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <button type="submit">Masuk</button>
    </form>
@endsection
