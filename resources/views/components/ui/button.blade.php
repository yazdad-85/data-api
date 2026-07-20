@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $variant = in_array($variant, ['primary', 'secondary', 'danger', 'ghost'], true)
        ? $variant
        : 'primary';
    $classes = 'btn btn-'.$variant;
@endphp

@if ($href)
    <a
        href="{{ $disabled ? '#' : $href }}"
        @if ($disabled) aria-disabled="true" tabindex="-1" @endif
        {{ $attributes->class([$classes, 'is-disabled' => $disabled]) }}
    >{{ $slot }}</a>
@else
    <button
        type="{{ $type }}"
        @disabled($disabled)
        {{ $attributes->class([$classes]) }}
    >{{ $slot }}</button>
@endif
