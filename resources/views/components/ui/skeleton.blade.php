@props([
    'rows' => 3,
])

@php
    $rows = max(1, (int) $rows);
@endphp

<div {{ $attributes->class(['skeleton']) }} aria-hidden="true">
    @for ($i = 0; $i < $rows; $i++)
        <div class="skeleton__row"></div>
    @endfor
</div>
