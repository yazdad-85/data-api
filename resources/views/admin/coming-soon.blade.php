@extends('layouts.admin')

@section('title', $title)
@section('breadcrumb', $title)

@section('content')
    <x-ui.empty-state
        :title="$title"
        description="Fitur ini sedang disiapkan dan akan tersedia pada tahap berikutnya."
    >
        <x-ui.badge tone="warn">Segera hadir</x-ui.badge>
    </x-ui.empty-state>
@endsection
