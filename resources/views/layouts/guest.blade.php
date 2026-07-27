<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $branding = app_branding();
        $pageTitle = trim($__env->yieldContent('title'));
        $documentTitle = $pageTitle !== '' ? $pageTitle : $branding['name'];
    @endphp
    <title>{{ $documentTitle }}</title>
    @if ($branding['favicon_url'])
        <link rel="icon" href="{{ $branding['favicon_url'] }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell" data-auth-shell>
        @yield('hero')

        <main class="auth-main">
            <div class="auth-panel">
                @yield('content')
            </div>
        </main>
    </div>
    <footer class="auth-footer">
        <span class="font-display" style="font-weight:600;">{{ $branding['name'] }}</span>
        <span aria-hidden="true"> · </span>
        <span>&copy; {{ now()->year }}</span>
    </footer>
</body>
</html>
