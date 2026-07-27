<aside class="admin-sidebar" aria-label="Navigasi admin">
    @php
        $branding = app_branding();
    @endphp
    <div class="admin-sidebar__brand font-display">
        @if ($branding['logo_url'])
            <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] }}" style="max-height: 36px; max-width: 180px;">
        @else
            {{ $branding['name'] }}
        @endif
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
