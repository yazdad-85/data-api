<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $branding = app_branding();
    @endphp
    <title>@yield('title', $branding['name'])</title>
    @if ($branding['favicon_url'])
        <link rel="icon" href="{{ $branding['favicon_url'] }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="guest-shell">
        <main class="guest-main">
            <div class="guest-card">
                @yield('content')
            </div>
        </main>
        @include('partials.footer')
    </div>
</body>
</html>
