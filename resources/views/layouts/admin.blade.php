<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Pusat Data') — Pusat Data</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="admin-shell">
        @include('partials.admin-sidebar')
        <div class="admin-sidebar-backdrop" data-sidebar-backdrop></div>
        <div class="admin-main">
            @include('partials.admin-header')
            <div class="admin-content">
                @yield('content')
            </div>
            @include('partials.footer')
        </div>
    </div>
</body>
</html>
