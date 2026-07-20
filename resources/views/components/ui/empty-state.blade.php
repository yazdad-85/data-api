@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class(['empty-state']) }}>
    <h1 class="empty-state__title font-display">{{ $title }}</h1>

    @if ($description)
        <p class="empty-state__description">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="empty-state__cta">
            {{ $slot }}
        </div>
    @endif
</div>
