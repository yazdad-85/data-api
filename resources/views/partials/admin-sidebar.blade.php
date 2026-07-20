<aside class="admin-sidebar" aria-label="Navigasi admin">
    <div class="admin-sidebar__brand font-display">Pusat Data</div>
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
