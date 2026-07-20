<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Pusat Data')</title>
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
