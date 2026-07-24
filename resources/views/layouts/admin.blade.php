<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $authUser = auth()->user();
        $appName = 'Pusat Data';

        if ($authUser?->isSuperAdmin()) {
            // Super Admin: tab hanya nama aplikasi (bukan nama lembaga yang sedang dilihat).
            $documentTitle = $appName;
        } elseif ($authUser?->isAdminLembaga() && $authUser->lembaga) {
            $lembagaNama = $authUser->lembaga->nama;
            $documentTitle = $pageTitle !== '' && $pageTitle !== $appName
                ? $pageTitle.' — '.$lembagaNama.' — '.$appName
                : $lembagaNama.' — '.$appName;
        } else {
            $documentTitle = $pageTitle !== '' && $pageTitle !== $appName
                ? $pageTitle.' — '.$appName
                : $appName;
        }
    @endphp
    <title>{{ $documentTitle }}</title>
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
