<aside class="admin-sidebar" aria-label="Navigasi admin">
    @php
        $branding = app_branding();
    @endphp
    <div class="admin-sidebar__brand font-display" style="display: flex; align-items: center; gap: 0.65rem;">
        @if ($branding['logo_url'])
            <img
                src="{{ $branding['logo_url'] }}"
                alt=""
                class="admin-sidebar__logo"
                style="max-height: 36px; max-width: 48px; object-fit: contain; flex-shrink: 0;"
            >
        @endif
        <span class="admin-sidebar__brand-name">{{ $branding['name'] }}</span>
    </div>
    <nav class="admin-sidebar__nav">
        @foreach ($menu as $item)
            @php
                $params = $item['params'] ?? [];
                $isActive = isset($params['feature'])
                    ? request()->routeIs($item['route']) && request()->route('feature') === $params['feature']
                    : request()->routeIs($item['route']);
            @endphp
            <a
                href="{{ route($item['route'], $params) }}"
                class="admin-sidebar__link{{ $isActive ? ' is-active' : '' }}{{ empty($item['available']) ? ' is-soon' : '' }}"
                @if ($isActive) aria-current="page" @endif
            >
                <span>{{ $item['label'] }}</span>
                @if (empty($item['available']))
                    <span class="admin-sidebar__badge">Segera</span>
                @endif
            </a>
        @endforeach
    </nav>
</aside>
