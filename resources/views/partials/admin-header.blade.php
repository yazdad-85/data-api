@php
    $roleLabel = match ($authUser?->role) {
        'super_admin' => 'Super Admin',
        'admin_lembaga' => 'Admin Lembaga',
        default => $authUser?->role ?? '',
    };
@endphp
<header class="admin-header">
    <div class="admin-header__start">
        <button type="button" class="admin-header__menu" data-sidebar-toggle aria-label="Buka menu">
            Menu
        </button>
        <nav class="admin-header__breadcrumb" aria-label="Breadcrumb">
            @hasSection('breadcrumb')
                @yield('breadcrumb')
            @else
                @yield('title', 'Dashboard')
            @endif
        </nav>
    </div>
    <div class="admin-header__user">
        @if ($authUser)
            <a href="{{ route('admin.profile.show') }}" class="admin-header__name">{{ $authUser->name }}</a>
            <span class="admin-header__role">{{ $roleLabel }}</span>
            @if ($authUser->isAdminLembaga() && $authUser->lembaga)
                <span class="admin-header__lembaga">{{ $authUser->lembaga->nama }}</span>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="admin-header__logout">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        @endif
    </div>
</header>
