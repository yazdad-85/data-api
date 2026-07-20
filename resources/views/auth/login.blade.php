@extends('layouts.guest')

@section('title', 'Masuk — Pusat Data')

@section('content')
    <h1>Pusat Data</h1>
    <p>Masuk ke panel administrasi.</p>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
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
