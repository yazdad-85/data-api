@extends('layouts.guest')

@php
    $branding = app_branding();
@endphp

@section('title', 'Masuk — '.$branding['name'])

@section('content')
    @if ($branding['logo_url'])
        <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] }}" style="max-height: 64px; max-width: 220px;">
    @endif
    <h1>{{ $branding['name'] }}</h1>
    <p>Masuk ke panel administrasi.</p>

    @if ($errors->any())
        <p class="guest-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <button type="submit">Masuk</button>
    </form>
@endsection
