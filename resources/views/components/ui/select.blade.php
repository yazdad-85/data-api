@props([
    'name',
    'label' => null,
    'value' => null,
    'required' => false,
    'error' => null,
    'hint' => null,
    'id' => null,
])

@php
    $id = $id ?? $name;
    $describedBy = $error ? $id.'-error' : ($hint ? $id.'-hint' : null);
@endphp

<div class="field">
    @if ($label)
        <label for="{{ $id }}" class="field-label">
            {{ $label }}
            @if ($required)
                <span class="field-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{-- Options slot: callers mark selected, e.g. @selected(old('status', $current) === 'active'). The value prop is for documentation / caller convenience only (slot evaluates in parent scope). --}}
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @required($required)
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class(['field-control']) }}
    >
        {{ $slot }}
    </select>

    @if ($error)
        <p id="{{ $id }}-error" class="field-error">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>
    @endif
</div>
