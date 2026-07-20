@props([
    'tone' => 'neutral',
])

@php
    $tone = in_array($tone, ['ok', 'warn', 'danger', 'neutral', 'brand'], true)
        ? $tone
        : 'neutral';
@endphp

<span {{ $attributes->class(['badge', 'badge-'.$tone]) }}>{{ $slot }}</span>
