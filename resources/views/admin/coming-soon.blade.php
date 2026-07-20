@extends('layouts.admin')

@section('title', $title)
@section('breadcrumb', $title)

@section('content')
    <div class="coming-soon">
        <h1 class="font-display">{{ $title }}</h1>
        <p class="coming-soon__lead">Segera hadir</p>
        <p>Fitur ini sedang disiapkan dan akan tersedia pada tahap berikutnya.</p>
    </div>
@endsection
